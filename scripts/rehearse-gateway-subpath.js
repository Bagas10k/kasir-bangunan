/**
 * rehearse-gateway-subpath.js
 * Isolated staging rehearsal for /kasir-bangunan/ gateway subpath proxy behavior.
 * Uses installed dependencies from /home/ubuntu/penelitian-pola-pikir-ai/node_modules.
 * NEVER touches live gateway service, live DB, or production files.
 */

import http from 'node:http';
import { createHash } from 'node:crypto';
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

export async function runStagingRehearsal() {
  const results = {
    test: 'gateway_subpath_isolated_staging',
    timestamp: new Date().toISOString(),
    assertions: {},
    allPassed: false
  };

  let upstreamServer = null;
  let gatewayServer = null;

  // Track upstream hits
  const upstreamHits = {
    total: 0,
    requests: []
  };

  try {
    // 1. Setup mock upstream (representing Laravel kasir-bangunan backend on loopback)
    upstreamServer = http.createServer((req, res) => {
      upstreamHits.total++;
      let bodyChunks = [];

      req.on('data', (chunk) => {
        bodyChunks.push(chunk);
      });

      req.on('end', () => {
        const rawBody = Buffer.concat(bodyChunks).toString('utf8');
        upstreamHits.requests.push({
          method: req.method,
          url: req.url,
          headers: req.headers,
          bodyLength: Buffer.byteLength(rawBody),
          bodyHash: createHash('sha256').update(rawBody).digest('hex')
        });

        if (req.method === 'GET' && req.url === '/') {
          // Laravel redirects unauthenticated GET / to /kasir-bangunan/login
          res.writeHead(302, {
            'Location': '/kasir-bangunan/login',
            'Content-Type': 'text/plain'
          });
          res.end('Redirecting to login');
        } else if (req.method === 'POST' && req.url === '/login') {
          // Simulate login authentication endpoint
          res.writeHead(200, {
            'Content-Type': 'application/json',
            'Set-Cookie': 'kasir_session=synthetic_auth_ok; Path=/kasir-bangunan; HttpOnly'
          });
          res.end(JSON.stringify({
            authenticated: true,
            receivedUrl: req.url,
            bodyLength: rawBody.length,
            hasCookie: Boolean(req.headers['cookie'])
          }));
        } else {
          res.writeHead(404, { 'Content-Type': 'text/plain' });
          res.end('Upstream Not Found');
        }
      });
    });

    await new Promise((resolve) => upstreamServer.listen(0, '127.0.0.1', resolve));
    const upstreamPort = upstreamServer.address().port;

    // 2. Setup disposable Express gateway on loopback
    const app = express();
    app.disable('x-powered-by');

    // Mount sibling paths to test negative isolation
    app.use('/pbj', (req, res) => {
      res.status(200).json({ sibling: 'pbj', handledBy: 'sibling_mock' });
    });

    app.use('/api', (req, res) => {
      res.status(200).json({ sibling: 'api', handledBy: 'sibling_mock' });
    });

    // Mount /kasir-bangunan proxy middleware using exact pattern from server.js:287-295
    app.use('/kasir-bangunan', createProxyMiddleware({
      target: `http://127.0.0.1:${upstreamPort}`,
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

    // Gateway fallback
    app.use((req, res) => {
      res.status(404).send('Gateway 404: Not Found');
    });

    gatewayServer = http.createServer(app);
    await new Promise((resolve) => gatewayServer.listen(0, '127.0.0.1', resolve));
    const gatewayPort = gatewayServer.address().port;

    // ==========================================
    // EXECUTE ASSERTIONS
    // ==========================================

    // Assertion 1: GET /kasir-bangunan/ reaches upstream '/' and Location has '/kasir-bangunan' prefix
    const resGet = await makeRequest({
      hostname: '127.0.0.1',
      port: gatewayPort,
      path: '/kasir-bangunan/',
      method: 'GET'
    });

    const upstreamReq1 = upstreamHits.requests[0];
    const getReachedRoot = upstreamReq1 && upstreamReq1.method === 'GET' && upstreamReq1.url === '/';
    const locationPrefixPreserved = resGet.statusCode === 302 && 
      resGet.headers['location'] && 
      resGet.headers['location'].startsWith('/kasir-bangunan');

    results.assertions.assertion_1_get_reaches_upstream_root_and_preserves_location_prefix = {
      passed: Boolean(getReachedRoot && locationPrefixPreserved),
      upstreamUrl: upstreamReq1 ? upstreamReq1.url : null,
      statusCode: resGet.statusCode,
      locationHeader: resGet.headers['location'],
      forwardedHost: upstreamReq1 ? upstreamReq1.headers['host'] : null,
      forwardedProto: upstreamReq1 ? upstreamReq1.headers['x-forwarded-proto'] : null
    };

    // Assertion 2: POST /kasir-bangunan/login reaches upstream /login with intact urlencoded body and cookie
    const syntheticCookie = 'synthetic_session=neutral_token_12345; XSRF-TOKEN=neutral_xsrf_token';
    const syntheticBody = '_token=neutral_csrf_abc123&username=synthetic_cashier&password=neutral_synthetic_secret';
    const expectedBodyHash = createHash('sha256').update(syntheticBody).digest('hex');

    const resPost = await makeRequest({
      hostname: '127.0.0.1',
      port: gatewayPort,
      path: '/kasir-bangunan/login',
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Content-Length': Buffer.byteLength(syntheticBody),
        'Cookie': syntheticCookie
      }
    }, syntheticBody);

    const upstreamReq2 = upstreamHits.requests[1];
    const postReachedLogin = upstreamReq2 && upstreamReq2.method === 'POST' && upstreamReq2.url === '/login';
    const cookiePreserved = upstreamReq2 && upstreamReq2.headers['cookie'] === syntheticCookie;
    const bodyLengthMatches = upstreamReq2 && upstreamReq2.bodyLength === Buffer.byteLength(syntheticBody);
    const bodyHashMatches = upstreamReq2 && upstreamReq2.bodyHash === expectedBodyHash;

    results.assertions.assertion_2_post_login_intact_body_and_cookie = {
      passed: Boolean(postReachedLogin && cookiePreserved && bodyLengthMatches && bodyHashMatches && resPost.statusCode === 200),
      upstreamUrl: upstreamReq2 ? upstreamReq2.url : null,
      cookieIntact: cookiePreserved,
      bodyLengthMatches: bodyLengthMatches,
      bodyHashMatches: bodyHashMatches,
      gatewayStatusCode: resPost.statusCode
    };

    // Assertion 3: Unrelated siblings /pbj and /api must NOT reach kasir upstream
    const upstreamHitsBeforeSiblings = upstreamHits.total;

    const resPbj = await makeRequest({
      hostname: '127.0.0.1',
      port: gatewayPort,
      path: '/pbj/overview',
      method: 'GET'
    });

    const resApi = await makeRequest({
      hostname: '127.0.0.1',
      port: gatewayPort,
      path: '/api/health',
      method: 'GET'
    });

    const upstreamHitsAfterSiblings = upstreamHits.total;
    const siblingsDidNotHitUpstream = (upstreamHitsBeforeSiblings === upstreamHitsAfterSiblings);

    results.assertions.assertion_3_unrelated_siblings_isolated = {
      passed: siblingsDidNotHitUpstream && resPbj.statusCode === 200 && resApi.statusCode === 200,
      pbjStatusCode: resPbj.statusCode,
      apiStatusCode: resApi.statusCode,
      extraUpstreamHits: upstreamHitsAfterSiblings - upstreamHitsBeforeSiblings
    };

    // Assertion 4: No gateway namespace escape
    const resEscape = await makeRequest({
      hostname: '127.0.0.1',
      port: gatewayPort,
      path: '/kasir-bangunan-unmatched',
      method: 'GET'
    });

    const upstreamHitsAfterEscape = upstreamHits.total;
    const noEscapeHitUpstream = (upstreamHitsAfterEscape === upstreamHitsAfterSiblings);

    results.assertions.assertion_4_no_namespace_escape = {
      passed: noEscapeHitUpstream && resEscape.statusCode === 404,
      escapeAttemptStatusCode: resEscape.statusCode,
      extraUpstreamHits: upstreamHitsAfterEscape - upstreamHitsAfterSiblings
    };

    // Evaluate overall pass
    results.allPassed = Object.values(results.assertions).every(a => a.passed === true);

  } finally {
    // 5. Cleanup both owned listeners even on errors
    if (upstreamServer && upstreamServer.listening) {
      await new Promise((resolve) => upstreamServer.close(resolve));
    }
    if (gatewayServer && gatewayServer.listening) {
      await new Promise((resolve) => gatewayServer.close(resolve));
    }
    results.cleanupComplete = true;
  }

  return results;
}

if (process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1]) {
  runStagingRehearsal()
    .then((res) => {
      console.log(JSON.stringify(res, null, 2));
      process.exit(res.allPassed ? 0 : 1);
    })
    .catch((err) => {
      console.error(JSON.stringify({ error: err.message, stack: err.stack }));
      process.exit(1);
    });
}
