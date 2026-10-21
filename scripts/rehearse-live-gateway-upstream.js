/**
 * rehearse-live-gateway-upstream.js
 * Isolated Express gateway proxy rehearsal directly targeting live container upstream (127.0.0.1:18733).
 * Tests routing, header preservation, assets, POST body handling, cookie flow, and sibling isolation.
 * Does NOT touch server.js, production DB, or public endpoints.
 */

import http from 'node:http';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);
const express = require('/home/ubuntu/penelitian-pola-pikir-ai/node_modules/express');
const { createProxyMiddleware } = require('/home/ubuntu/penelitian-pola-pikir-ai/node_modules/http-proxy-middleware');

function makeRequest(options, postData = null) {
  return new Promise((resolve, reject) => {
    const req = http.request(options, (res) => {
      let data = '';
      res.on('data', (chunk) => { data += chunk; });
      res.on('end', () => {
        resolve({
          statusCode: res.statusCode,
          headers: res.headers,
          body: data
        });
      });
    });
    req.on('error', reject);
    if (postData) {
      req.write(postData);
    }
    req.end();
  });
}

export async function runLiveGatewayRehearsal() {
  const results = {
    test: 'gateway_subpath_live_upstream_rehearsal',
    target: 'http://127.0.0.1:18733',
    timestamp: new Date().toISOString(),
    assertions: {},
    allPassed: false
  };

  let gatewayServer = null;

  try {
    const app = express();
    app.disable('x-powered-by');

    // Sibling route isolation tests
    app.use('/pbj', (req, res) => {
      res.status(200).json({ sibling: 'pbj', handledBy: 'sibling_mock' });
    });

    app.use('/api', (req, res) => {
      res.status(200).json({ sibling: 'api', handledBy: 'sibling_mock' });
    });

    // Production-identical proxy middleware configuration for /kasir-bangunan
    app.use('/kasir-bangunan', createProxyMiddleware({
      target: 'http://127.0.0.1:18733',
      changeOrigin: true,
      pathRewrite: (p) => p.replace(/^\/kasir-bangunan(?=\/|$)/, '') || '/',
      on: {
        proxyReq: (proxyReq) => {
          proxyReq.setHeader('Host', 'www.jajandigital.web.id');
          proxyReq.setHeader('X-Forwarded-Proto', 'https');
          proxyReq.setHeader('X-Forwarded-Prefix', '/kasir-bangunan');
        }
      }
    }));

    app.use((req, res) => {
      res.status(404).send('Gateway 404: Not Found');
    });

    gatewayServer = http.createServer(app);
    await new Promise((resolve) => gatewayServer.listen(0, '127.0.0.1', resolve));
    const gatewayPort = gatewayServer.address().port;
    results.gatewayEphemeralPort = gatewayPort;

    // 1. GET /kasir-bangunan/ -> Upstream redirects 302 to https://www.jajandigital.web.id/kasir-bangunan/login
    const resRoot = await makeRequest({
      hostname: '127.0.0.1',
      port: gatewayPort,
      path: '/kasir-bangunan/',
      method: 'GET'
    });

    const rootRedirectsCorrectly = resRoot.statusCode === 302 &&
      resRoot.headers['location'] &&
      resRoot.headers['location'].includes('/kasir-bangunan/login');

    results.assertions.assertion_1_get_root_redirect = {
      passed: Boolean(rootRedirectsCorrectly),
      statusCode: resRoot.statusCode,
      location: resRoot.headers['location'],
      hasSessionCookie: Boolean(resRoot.headers['set-cookie'])
    };

    // 2. GET /kasir-bangunan/login -> Returns 200 OK login page HTML
    const resLogin = await makeRequest({
      hostname: '127.0.0.1',
      port: gatewayPort,
      path: '/kasir-bangunan/login',
      method: 'GET'
    });

    const loginHtmlValid = resLogin.statusCode === 200 &&
      resLogin.body.includes('LOGIN KASIR') &&
      resLogin.body.includes('action="https://www.jajandigital.web.id/kasir-bangunan/login"');

    // Extract CSRF token and cookie for POST test
    const tokenMatch = resLogin.body.match(/name="_token" value="([^"]+)"/);
    const csrfToken = tokenMatch ? tokenMatch[1] : null;
    const cookies = resLogin.headers['set-cookie'] || [];
    const cookieHeader = cookies.map(c => c.split(';')[0]).join('; ');

    results.assertions.assertion_2_get_login_page = {
      passed: Boolean(loginHtmlValid && csrfToken),
      statusCode: resLogin.statusCode,
      foundCsrfToken: Boolean(csrfToken),
      cookieCount: cookies.length,
      formActionValid: resLogin.body.includes('https://www.jajandigital.web.id/kasir-bangunan/login')
    };

    // 3. GET Static asset /kasir-bangunan/build/manifest.json -> Returns 200 OK JSON
    const resManifest = await makeRequest({
      hostname: '127.0.0.1',
      port: gatewayPort,
      path: '/kasir-bangunan/build/manifest.json',
      method: 'GET'
    });

    const manifestValid = resManifest.statusCode === 200 &&
      resManifest.headers['content-type'] &&
      resManifest.headers['content-type'].includes('application/json') &&
      resManifest.body.includes('resources/css/app.css');

    results.assertions.assertion_3_get_static_asset_manifest = {
      passed: Boolean(manifestValid),
      statusCode: resManifest.statusCode,
      contentType: resManifest.headers['content-type'],
      bytesReceived: Buffer.byteLength(resManifest.body)
    };

    // 4. Resolve the current hashed CSS asset from Vite's manifest.
    const cssPath = manifestValid ? JSON.parse(resManifest.body)?.['resources/css/app.css']?.file : null;
    const resCss = cssPath && /^assets\/[a-zA-Z0-9_.-]+\.css$/.test(cssPath)
      ? await makeRequest({
        hostname: '127.0.0.1',
        port: gatewayPort,
        path: '/kasir-bangunan/build/' + cssPath,
        method: 'GET'
      })
      : { statusCode: 0, headers: {}, body: '' };

    const cssValid = resCss.statusCode === 200 &&
      resCss.headers['content-type'] &&
      resCss.headers['content-type'].includes('text/css') &&
      resCss.body.length > 5000;

    results.assertions.assertion_4_get_compiled_css = {
      passed: Boolean(cssValid),
      statusCode: resCss.statusCode,
      contentType: resCss.headers['content-type'],
      cssBytes: Buffer.byteLength(resCss.body)
    };

    // 5. POST /kasir-bangunan/login with synthetic non-existent credentials -> 302 Redirect (No DB mutation)
    const postBody = `_token=${encodeURIComponent(csrfToken || '')}&email=synthetic.cashier%40example.test&password=synthetic_nonexistent_pwd_999`;
    const resPost = await makeRequest({
      hostname: '127.0.0.1',
      port: gatewayPort,
      path: '/kasir-bangunan/login',
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Content-Length': Buffer.byteLength(postBody),
        'Cookie': cookieHeader,
        'Host': 'www.jajandigital.web.id'
      }
    }, postBody);

    // Upstream Laravel auth rejects non-existent user and issues a redirect back (302) with session cookie
    const postHandledSafely = resPost.statusCode === 302 &&
      resPost.headers['location'] === 'https://www.jajandigital.web.id/kasir-bangunan/login' &&
      Boolean(resPost.headers['set-cookie']);

    results.assertions.assertion_5_post_login_synthetic_dummy = {
      passed: Boolean(postHandledSafely),
      statusCode: resPost.statusCode,
      redirectLocation: resPost.headers['location'],
      cookiesUpdated: Boolean(resPost.headers['set-cookie']),
      note: 'No real DB mutation: dummy non-existent credentials correctly rejected by Laravel auth'
    };

    // 6. Sibling isolation: /pbj and /api must never route to 18733
    const resPbj = await makeRequest({
      hostname: '127.0.0.1',
      port: gatewayPort,
      path: '/pbj/test-route',
      method: 'GET'
    });

    const resApi = await makeRequest({
      hostname: '127.0.0.1',
      port: gatewayPort,
      path: '/api/test-route',
      method: 'GET'
    });

    const siblingsIsolated = resPbj.statusCode === 200 &&
      resPbj.body.includes('"sibling":"pbj"') &&
      resApi.statusCode === 200 &&
      resApi.body.includes('"sibling":"api"');

    results.assertions.assertion_6_sibling_routes_isolated = {
      passed: Boolean(siblingsIsolated),
      pbjStatusCode: resPbj.statusCode,
      apiStatusCode: resApi.statusCode
    };

    // 7. Namespace escape protection: /kasir-bangunan-invalid must not route to upstream
    const resEscape = await makeRequest({
      hostname: '127.0.0.1',
      port: gatewayPort,
      path: '/kasir-bangunan-invalid',
      method: 'GET'
    });

    results.assertions.assertion_7_namespace_escape_guarded = {
      passed: resEscape.statusCode === 404,
      statusCode: resEscape.statusCode
    };

    results.allPassed = Object.values(results.assertions).every(a => a.passed === true);

  } finally {
    if (gatewayServer && gatewayServer.listening) {
      await new Promise((resolve) => gatewayServer.close(resolve));
    }
  }

  return results;
}

if (process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1]) {
  runLiveGatewayRehearsal()
    .then((res) => {
      console.log(JSON.stringify(res, null, 2));
      process.exit(res.allPassed ? 0 : 1);
    })
    .catch((err) => {
      console.error(JSON.stringify({ error: err.message, stack: err.stack }));
      process.exit(1);
    });
}
