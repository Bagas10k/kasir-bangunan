#!/usr/bin/env python3
"""Regression checks for the documentation helper; synthetic scratch files only."""
import contextlib
import importlib.util
import io
import json
import os
from pathlib import Path
import sys
import tempfile
import unittest

sys.dont_write_bytecode = True
HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('documentation_sync', HERE.parent / 'sinkron.py')
sync = importlib.util.module_from_spec(spec)
spec.loader.exec_module(sync)


class DocumentationSyncTest(unittest.TestCase):
    def setUp(self):
        self.scratch = tempfile.TemporaryDirectory(prefix='tb01-doc-test-', dir=os.environ.get('TMPDIR'))
        self.addCleanup(self.scratch.cleanup)
        self.root = Path(self.scratch.name).resolve()
        self.real_globals = (sync.ROOT, sync.OUT, sync.BASELINE, sync.CANONICAL)
        self.addCleanup(self.restore_globals)
        sync.ROOT = self.root
        sync.OUT = self.root / 'docx'
        sync.BASELINE = sync.OUT / 'sinkron.json'
        sync.CANONICAL = sync.OUT / 'sumber/render_map.py'
        self.put('app/Example.php', '<?php\n// synthetic documentation fixture; never executed\n')
        self.put('app/Additional.php', '<?php\n// second watched file\n')
        self.put('docx/sinkron.py', '# synthetic recipe identity only\n')
        self.put('docx/sumber/render_map.py', (HERE / 'render_map.py').read_text(encoding='utf-8'))
        self.package = {
            'title': 'Synthetic flow',
            'steps': [{'id': 'S1', 'label': 'Inspect source', 'actor': 'Test', 'before': 'File exists',
                       'after': 'Source inspected', 'evidence': 'app/Example.php:1', 'tables': {}}],
            'edges': [], 'unknowns': ['Synthetic fixture, not tb01 application behavior.'],
            'proofs': [{'step_id': 'S1', 'path': 'app/Example.php', 'start': 1, 'end': 2, 'label': 'Fixture'}],
        }
        self.put_json('docx/sumber/modul/01-example.json', self.package)
        self.put_json('docx/sumber/lintas-modul.json', {'title': 'Synthetic', 'order': ['01-example'], 'edges': [], 'unknowns': [], 'observations': []})
        _, packages, model, tool, _ = sync.load_models()
        self.put('docx/model.json', sync.encoded(model))
        self.put('docx/ALUR.md', tool.render(model))
        self.put('docx/modul/01-example.md', tool.render(sync.canonical_model(self.package)))
        # Deliberately opaque output bytes: only hash integrity is under test here.
        self.put('docx/ALUR-SISTEM.docx', b'synthetic-output-hash-fixture')
        outputs = [sync.OUT / name for name in ('model.json', 'ALUR.md', 'modul/01-example.md', 'ALUR-SISTEM.docx')]
        self.baseline = {'sources': sync.sources(), 'inputs': sync.inputs(), 'outputs': sync.hashes(outputs),
                         'counts': {'modules': 1, 'steps': 1}, 'source_digest': sync.digest(sync.encoded(sync.sources()))}
        self.put_json('docx/sinkron.json', self.baseline)

    def restore_globals(self):
        sync.ROOT, sync.OUT, sync.BASELINE, sync.CANONICAL = self.real_globals

    def put(self, relative, content):
        path = self.root / relative
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_bytes(content.encode('utf-8') if isinstance(content, str) else content)

    def put_json(self, relative, value):
        self.put(relative, sync.encoded(value))

    def check(self):
        output = io.StringIO()
        with contextlib.redirect_stdout(output):
            result = sync.check()
        return result, output.getvalue()

    def test_unchanged_artifacts_are_synchronized(self):
        self.assertEqual(0, self.check()[0])

    def test_check_does_not_modify_any_fixture_file(self):
        before = sync.hashes([p for p in self.root.rglob('*') if p.is_file()])
        self.assertEqual(0, self.check()[0])
        after = sync.hashes([p for p in self.root.rglob('*') if p.is_file()])
        self.assertEqual(before, after)

    def test_changed_source_is_detected(self):
        self.put('app/Example.php', '<?php\n// changed source\n')
        code, text = self.check()
        self.assertEqual(1, code)
        self.assertIn('SUMBER BERUBAH app/Example.php', text)

    def test_new_action_file_is_detected(self):
        self.put('app/NewAction.php', '<?php\n')
        self.assertIn('SUMBER BARU app/NewAction.php', self.check()[1])

    def test_removed_source_is_detected(self):
        (self.root / 'app/Additional.php').unlink()
        self.assertIn('SUMBER HILANG app/Additional.php', self.check()[1])

    def test_changed_narrative_is_detected(self):
        self.package['steps'][0]['after'] = 'New reviewed description'
        self.put_json('docx/sumber/modul/01-example.json', self.package)
        code, text = self.check()
        self.assertEqual(1, code)
        self.assertIn('RESEP BERUBAH', text)
        self.assertIn('MODEL tidak sesuai', text)

    def test_changed_word_document_is_detected(self):
        self.put('docx/ALUR-SISTEM.docx', b'edited fixture')
        self.assertIn('KELUARAN BERUBAH docx/ALUR-SISTEM.docx', self.check()[1])

    def test_missing_word_document_is_detected(self):
        (self.root / 'docx/ALUR-SISTEM.docx').unlink()
        self.assertIn('KELUARAN HILANG docx/ALUR-SISTEM.docx', self.check()[1])

    def test_build_refuses_unreviewed_source_drift_before_writes(self):
        self.put('app/Example.php', '<?php\n// changed\n')
        before = sync.hashes([p for p in sync.OUT.rglob('*') if p.is_file()])
        with self.assertRaisesRegex(ValueError, 'SUMBER BERUBAH'):
            sync.build(False)
        self.assertEqual(before, sync.hashes([p for p in sync.OUT.rglob('*') if p.is_file()]))

    def test_manual_output_edit_is_not_overwritten(self):
        self.put('docx/ALUR.md', 'manual content')
        with self.assertRaisesRegex(ValueError, 'Tidak menimpa'):
            sync.emit(sync.OUT / 'ALUR.md', b'new generated', self.baseline)
        self.assertEqual('manual content', (sync.OUT / 'ALUR.md').read_text())

    def test_invalid_evidence_range_is_rejected(self):
        with self.assertRaisesRegex(ValueError, 'Rentang bukti tidak ada'):
            sync.excerpt({'path': 'app/Example.php', 'start': 1, 'end': 999})

    def test_path_escape_and_environment_reads_are_rejected(self):
        for path in ('../outside.php', 'C:/outside.php', '.env', 'app/../../.env'):
            with self.subTest(path=path), self.assertRaises(ValueError):
                sync.safe_file(path)

    def test_missing_baseline_is_not_reported_as_synchronized(self):
        sync.BASELINE.unlink()
        code, text = self.check()
        self.assertEqual(1, code)
        self.assertIn('BELUM ADA BASELINE', text)


if __name__ == '__main__':
    unittest.main(verbosity=2)
