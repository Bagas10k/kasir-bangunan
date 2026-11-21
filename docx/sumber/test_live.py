#!/usr/bin/env python3
"""Tests for local live documentation. PHP fixtures are text, never executed."""
import importlib.util
import json
import os
from pathlib import Path
import sys
import tempfile
import threading
import time
import unittest
from urllib.error import HTTPError
from urllib.request import Request, urlopen

sys.dont_write_bytecode = True
HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('live_documentation', HERE.parent / 'live.py')
live = importlib.util.module_from_spec(spec)
spec.loader.exec_module(live)

CONTROLLER = '''<?php
namespace App\\Http\\Controllers;
use App\\Services\\Example;
class DemoController {
    public function __construct(private Example $engine) {}
    public function store() { return $this->engine->save(); }
}
'''
SERVICE = '''<?php
namespace App\\Services;
use Illuminate\\Support\\Facades\\DB;
class Example {
    public function save() {
        if (! $this->allowed()) { throw new \\RuntimeException("Denied { not a brace }"); }
        DB::table('sample_rows')->insert(['status' => 'draft']);
        return 1;
    }
    private function allowed() { return true; }
}
'''
ROUTES = '''<?php
use App\\Http\\Controllers\\DemoController;
Route::middleware('auth')->prefix('v2')->name('v2.')->group(function () {
    Route::post('/examples', [DemoController::class, 'store'])->name('examples.store');
});
'''


class ParsingTest(unittest.TestCase):
    def test_nested_route_prefix_name_and_middleware(self):
        routes, warnings = live.parse_routes('routes/web.php', ROUTES)
        self.assertEqual([], warnings)
        self.assertEqual(1, len(routes))
        self.assertEqual(('POST', '/v2/examples', 'v2.examples.store'), (routes[0]['http'], routes[0]['path'], routes[0]['name']))
        self.assertEqual(['auth'], routes[0]['middleware'])
        self.assertEqual('App\\Http\\Controllers\\DemoController::store', routes[0]['entry'])

    def test_strings_and_comments_do_not_break_method_boundaries(self):
        symbols, _ = live.parse_file('app/Services/Example.php', SERVICE)
        self.assertEqual(2, len(symbols))
        method = symbols['App\\Services\\Example::save']
        self.assertIn('return 1;', method['code'])
        self.assertNotIn('private function allowed', method['code'])

    def test_typed_promoted_service_and_local_calls_are_resolved(self):
        a, deps = live.parse_file('app/Http/Controllers/DemoController.php', CONTROLLER)
        b, _ = live.parse_file('app/Services/Example.php', SERVICE)
        symbols = a | b
        for node in symbols.values():
            live.analyze_symbol(node, deps, symbols)
        self.assertIn('App\\Services\\Example::save', [c['target'] for c in a['App\\Http\\Controllers\\DemoController::store']['calls']])
        method = b['App\\Services\\Example::save']
        self.assertIn({'table': 'sample_rows', 'operations': ['create']}, method['tables'])
        self.assertIn('draft', method['statuses'])
        self.assertIn('App\\Services\\Example::allowed', [c['target'] for c in method['calls']])

    def test_container_service_calls_are_resolved(self):
        code = '<?php namespace App; use App\\Services\\Example; class A { public function go() { return app(Example::class)->save(); } }'
        symbols, deps = live.parse_file('app/A.php', code)
        other, _ = live.parse_file('app/Services/Example.php', SERVICE)
        symbols.update(other)
        node = symbols['App\\A::go']
        live.analyze_symbol(node, deps, symbols)
        self.assertIn('App\\Services\\Example::save', [c['target'] for c in node['calls']])

    def test_unfinished_php_is_rejected_not_rebuilt_as_empty(self):
        with self.assertRaises(ValueError):
            live.parse_file('app/Example.php', SERVICE[:-3])

    def test_dynamic_handler_is_not_invented(self):
        routes, warnings = live.parse_routes('routes/web.php', "<?php Route::get('/dynamic', function () { return 'ok'; });")
        self.assertEqual('Closure / handler dinamis', routes[0]['entry'])
        self.assertTrue(warnings)

    def test_sensitive_literals_are_redacted(self):
        code = "$password = Hash::make('SYNTHETIC_TEST_SECRET'); $email = 'fixture@example.invalid';"
        result = live.safe_code(code)
        self.assertNotIn('SYNTHETIC_TEST_SECRET', result)
        self.assertNotIn('fixture@example.invalid', result)
        self.assertIn('[REDACTED]', result)

    def test_heredoc_body_braces_are_not_php_blocks(self):
        code = "<?php namespace App; class A { public function go() { $text = <<<'HTML'\n{ not PHP } ' quote\nHTML;\nreturn $text; } }"
        symbols, _ = live.parse_file('app/A.php', code)
        self.assertEqual(1, len(symbols))
        self.assertIn('return $text;', symbols['App\\A::go']['code'])

    def test_class_account_constants_are_extracted_without_executing_php(self):
        code = "<?php namespace App; class A { private const CASH = '1101'; private const SECRET = 'SYNTHETIC_SECRET'; public function go() { return self::CASH; } }"
        symbols, _ = live.parse_file('app/A.php', code)
        constants = symbols['App\\A::go']['constants']
        self.assertEqual('1101', constants['CASH']['value'])
        self.assertEqual('[REDACTED]', constants['SECRET']['value'])

    def test_self_and_static_helpers_resolve_to_current_class(self):
        code = '<?php namespace App; class A { public function go() { self::check(); static::check(); } private static function check() {} }'
        symbols, deps = live.parse_file('app/A.php', code)
        node = symbols['App\\A::go']
        live.analyze_symbol(node, deps, symbols)
        self.assertEqual(['App\\A::check'], [c['target'] for c in node['calls']])


class LiveIntegrationTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='tb01-live-doc-test-', dir=os.environ.get('TMPDIR'))
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name).resolve()
        self.put('app/Http/Controllers/DemoController.php', CONTROLLER)
        self.put('app/Services/Example.php', SERVICE)
        self.put('routes/web.php', ROUTES)
        self.put('docx/sumber/alur-live.html', '<!doctype html><html><script type="application/json">__SNAPSHOT_JSON__</script></html>')
        self.state = live.SourceMap(self.root)
        self.server = live.ThreadingHTTPServer(('127.0.0.1', 0), live.handler_for(self.state))
        self.state.port = self.server.server_address[1]
        self.state.rebuild()
        self.base = f'http://127.0.0.1:{self.state.port}'
        self.thread = threading.Thread(target=self.server.serve_forever, daemon=True)
        self.thread.start()
        self.addCleanup(self.shutdown)

    def shutdown(self):
        self.state.closed.set()
        self.server.shutdown()
        self.server.server_close()
        self.thread.join(timeout=2)

    def put(self, relative, text):
        path = self.root / relative
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(text, encoding='utf-8')

    def get(self, path, headers=None):
        return urlopen(Request(self.base + path, headers=headers or {}), timeout=3)

    def test_real_snapshot_and_static_html_are_served(self):
        with self.get('/alur.html') as response:
            self.assertEqual(200, response.status)
            self.assertIn(b'sample_rows', response.read())
        with self.get('/api/snapshot?token='+self.state.access_token) as response:
            data = json.load(response)
            self.assertEqual(1, data['route_count'])
            self.assertEqual(3, len(data['routes'][0]['node_ids']))

    def test_live_watch_updates_code_status_and_written_html(self):
        version = self.state.version
        watcher = threading.Thread(target=self.state.watch, args=(0.05,), daemon=True)
        watcher.start()
        self.addCleanup(watcher.join, 1)
        changed = SERVICE.replace("'draft'", "'confirmed'")
        started = time.monotonic()
        self.put('app/Services/Example.php', changed)
        deadline = started + 5
        while time.monotonic() < deadline and self.state.version == version:
            time.sleep(0.03)
        self.assertNotEqual(version, self.state.version)
        with self.get('/api/snapshot?token='+self.state.access_token) as response:
            data = json.load(response)
        self.assertIn('confirmed', data['symbols']['App\\Services\\Example::save']['statuses'])
        self.assertIn(b'confirmed', (self.root/'docx/alur.html').read_bytes())

    def test_new_route_and_method_appear_and_deletion_disappears(self):
        self.put('routes/web.php', ROUTES + "\nRoute::get('/added', [DemoController::class, 'store']);\n")
        self.state.rebuild()
        self.assertEqual(2, self.state.snapshot['route_count'])
        self.put('routes/web.php', ROUTES)
        self.state.rebuild()
        self.assertEqual(1, self.state.snapshot['route_count'])

    def test_invalid_source_preserves_last_good_snapshot(self):
        previous = self.state.html
        self.put('app/Services/Example.php', SERVICE[:-3])
        with self.assertRaises(ValueError):
            self.state.rebuild()
        self.assertEqual(previous, self.state.html)

    def test_restoring_exact_last_good_source_clears_transient_error(self):
        self.state.last_error = 'Synthetic partial save error'
        self.assertFalse(self.state.rebuild())
        self.assertIsNone(self.state.last_error)

    def test_missing_token_and_foreign_origin_and_host_are_rejected(self):
        attempts = [('/api/snapshot', {}),
                    ('/api/snapshot?token=%E2%98%83', {}),
                    ('/api/snapshot?token='+self.state.access_token, {'Origin': 'https://untrusted.invalid'}),
                    ('/api/snapshot?token='+self.state.access_token, {'Host': 'untrusted.invalid'})]
        for path, headers in attempts:
            with self.subTest(path=path.split('?')[0], headers=headers), self.assertRaises(HTTPError) as error:
                self.get(path, headers)
            self.assertEqual(403, error.exception.code)

    def test_only_document_routes_are_served(self):
        for path in ('/.env', '/../../.env', '/app/Services/Example.php'):
            with self.subTest(path=path), self.assertRaises(HTTPError) as error:
                self.get(path)
            self.assertEqual(404, error.exception.code)

    def test_valid_null_origin_is_authorized_for_local_html_only_with_token(self):
        with self.get('/api/snapshot?token='+self.state.access_token, {'Origin': 'null'}) as response:
            self.assertEqual('null', response.headers['Access-Control-Allow-Origin'])

    def test_local_file_etag_preflight_is_authorized(self):
        request = Request(self.base+'/api/snapshot?token='+self.state.access_token, method='OPTIONS',
                          headers={'Origin': 'null', 'Access-Control-Request-Headers': 'if-none-match'})
        with urlopen(request, timeout=3) as response:
            self.assertEqual(204, response.status)
            self.assertEqual('If-None-Match', response.headers['Access-Control-Allow-Headers'])

    def test_line_relocation_refreshes_code_but_does_not_reapprove_old_prose(self):
        symbol = self.state.snapshot['symbols']['App\\Services\\Example::save']
        model = {'steps': [{'id': 'S1', 'label': 'Reviewed draft behavior', 'actor': 'Test',
                            'before': 'Existing source', 'after': 'Draft is written',
                            'evidence': f'app/Services/Example.php:{symbol["line"]}'}]}
        self.put('docx/model.json', json.dumps(model))
        self.put('docx/sinkron.json', json.dumps({'sources': self.state.scope.sources()}))
        self.state.rebuild()
        self.assertTrue(self.state.snapshot['routes'][0]['notes'][0]['current'])
        self.put('app/Services/Example.php', '\n\n' + SERVICE.replace("'draft'", "'confirmed'"))
        self.state.rebuild()
        updated = self.state.snapshot['symbols']['App\\Services\\Example::save']
        self.assertEqual(symbol['line']+2, updated['line'])
        self.assertIn('confirmed', updated['statuses'])
        self.assertFalse(self.state.snapshot['routes'][0]['notes'][0]['current'])

    def test_embedded_source_cannot_close_json_script(self):
        self.put('app/Services/Example.php', SERVICE.replace('return 1;', "return '</script><script>notExecutable()</script>';"))
        self.state.rebuild()
        self.assertNotIn(b'<script>notExecutable()', self.state.html)
        self.assertIn(b'\\u003c/script\\u003e', self.state.html)

    def install_business_fixture(self):
        symbol = self.state.snapshot['symbols']['App\\Services\\Example::save']
        model = {'steps': [{'id': 'S1', 'label': 'Reviewed fixture', 'actor': 'Admin',
                            'before': 'Input prepared', 'after': 'Draft saved',
                            'evidence': f'app/Services/Example.php:{symbol["line"]}'}]}
        definition = {'intro': 'Synthetic business journey', 'journeys': [{
            'id': 'barang-masuk', 'title': 'Barang masuk', 'subtitle': 'Synthetic test',
            'actor': 'Admin', 'start': 'Barang diterima', 'finish': 'Draf tersimpan',
            'summary': ['Isi penerimaan', 'Simpan draf'], 'links': [], 'steps': [{
                'id': 'save-draft', 'title': 'Simpan draf penerimaan', 'actor': 'Admin',
                'action': 'Isi supplier dan barang lalu simpan draf.', 'condition': 'Isi valid.',
                'result': 'Draf tersimpan; stok belum bertambah.', 'kind': 'action',
                'effects': {'stok': 'Belum berubah'}, 'branches': [], 'next': [], 'refs': ['S1']
            }]
        }]}
        self.put('docx/model.json', json.dumps(model))
        self.put('docx/sinkron.json', json.dumps({'sources': self.state.scope.sources()}))
        self.put('docx/sumber/alur-operasional.json', json.dumps(definition))
        self.state.rebuild()
        return definition

    def test_business_operations_are_primary_snapshot_with_source_proof(self):
        self.install_business_fixture()
        with self.get('/api/snapshot?token='+self.state.access_token) as response:
            data = json.load(response)
        journey = data['journeys'][0]
        self.assertEqual('Barang masuk', journey['title'])
        stage = journey['steps'][0]
        self.assertEqual('Belum berubah', stage['effects']['stok'])
        self.assertTrue(stage['current'])
        self.assertEqual('App\\Services\\Example::save', stage['evidence'][0]['node_ids'][0])

    def test_business_proof_relocates_and_narrative_is_not_silently_reapproved(self):
        self.install_business_fixture()
        before = self.state.snapshot['journeys'][0]['steps'][0]['evidence'][0]['line']
        self.put('app/Services/Example.php', '\n\n' + SERVICE.replace("'draft'", "'confirmed'"))
        self.state.rebuild()
        stage = self.state.snapshot['journeys'][0]['steps'][0]
        self.assertFalse(stage['current'])
        self.assertEqual(before+2, stage['evidence'][0]['line'])
        self.assertIn('confirmed', self.state.snapshot['symbols'][stage['evidence'][0]['node_ids'][0]]['statuses'])

    def test_unknown_business_transition_is_rejected(self):
        definition = self.install_business_fixture()
        definition['journeys'][0]['steps'][0]['branches'] = [
            {'label': 'Synthetic', 'result': 'Invalid next', 'next': 'missing-stage'}]
        self.put('docx/sumber/alur-operasional.json', json.dumps(definition))
        with self.assertRaisesRegex(ValueError, 'Tujuan tahap bisnis tidak ditemukan'):
            self.state.rebuild()

    def test_unknown_evidence_reference_is_rejected(self):
        definition = self.install_business_fixture()
        definition['journeys'][0]['steps'][0]['refs'] = ['MISSING']
        self.put('docx/sumber/alur-operasional.json', json.dumps(definition))
        with self.assertRaisesRegex(ValueError, 'Langkah bukti bisnis belum dipetakan'):
            self.state.rebuild()

    def test_cancel_diagram_branch_ends_instead_of_becoming_confirmation(self):
        definition = self.install_business_fixture()
        first = definition['journeys'][0]['steps'][0]
        second = dict(first, id='confirm', title='Konfirmasi final', next=[], branches=[])
        first['kind'] = 'decision'
        first['branches'] = [{'label': 'Batalkan draf', 'result': 'Tidak ada posting', 'next': ''},
                             {'label': 'Konfirmasi', 'result': 'Posting final', 'next': 'confirm'}]
        definition['journeys'][0]['steps'].append(second)
        self.put('docx/sumber/alur-operasional.json', json.dumps(definition))
        self.state.rebuild()
        diagram = (self.root/'docx/DIAGRAM.md').read_text(encoding='utf-8')
        self.assertIn('n0 -->|"Batalkan draf"| end0', diagram)
        self.assertIn('n0 -->|"Konfirmasi"| n1', diagram)
        self.assertNotIn('end0 -->', diagram)
        self.assertIn('app/Services/Example.php:', diagram)

    def test_manual_diagram_is_not_overwritten_and_does_not_publish_other_outputs(self):
        previous = (self.root/'docx/alur.html').read_bytes()
        self.put('docx/DIAGRAM.md', 'Synthetic manual diagram notes')
        self.put('app/Services/Example.php', SERVICE.replace("'draft'", "'changed'"))
        with self.assertRaisesRegex(ValueError, 'Tidak menimpa DIAGRAM.md'):
            self.state.rebuild()
        self.assertEqual('Synthetic manual diagram notes', (self.root/'docx/DIAGRAM.md').read_text())
        self.assertEqual(previous, (self.root/'docx/alur.html').read_bytes())


if __name__ == '__main__':
    unittest.main(verbosity=2)
