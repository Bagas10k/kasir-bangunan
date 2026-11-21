#!/usr/bin/env python3
"""Build/check source-derived documentation only. Never bootstrap PHP or access DB.

Check: python docx/sinkron.py --check (stdlib, Python 3.12+).
Build: python docx/sinkron.py --build (python-docx needed only for Word export).
Source drift requires retracing code, editing recipes, then --accept-reviewed-source.
"""
from __future__ import annotations

import argparse
from collections import defaultdict
from datetime import datetime, timezone
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import re
import subprocess
import sys
import tempfile

sys.dont_write_bytecode = True

ROOT = Path(__file__).resolve().parent.parent
OUT = ROOT / 'docx'
BASELINE = OUT / 'sinkron.json'
CANONICAL = OUT / 'sumber/render_map.py'
SOURCE_GROUPS = {
    'app': {'.php'}, 'routes': {'.php'}, 'database/migrations': {'.php'},
    'resources/views': {'.php'}, 'resources/js': {'.js', '.mjs', '.ts'},
    'resources/css': {'.css'},
}
EXTRA_SOURCES = ('bootstrap/app.php', 'config/v2.php', 'composer.json', 'package.json')
SHORT_OPS = {'R': 'read', 'C': 'create', 'U': 'update', 'D': 'delete'}
INTRO = (
    'Dokumen ini adalah tampilan alur dari kode working tree, bukan spesifikasi yang '
    'harus diikuti kode. Jika berbeda, kode menang. Setiap langkah mencatat pelaku, '
    'pemicu, guard, perubahan status/data, cabang gagal, dan bukti path:baris. '
    'Kutipan kode dibaca langsung ketika build; tidak ditulis ulang sebagai pseudocode.'
)
MAIN_FLOW = (
    ('Persiapan', 'Login/peran → master barang, pihak, satuan, kategori, sopir/kendaraan; impor opsional; saldo awal hanya saat sistem bersih.'),
    ('Persediaan', 'Supplier/produk tertaut → draf penerimaan → edit ATAU batal ATAU konfirmasi → stok/HPP + AP + jurnal; final terkunci.'),
    ('Utang supplier', 'AP yang sama → pilih Kas Utama / Bank Utama / Laci shift; admin posting langsung, kasir proposed → admin setujui ATAU tolak.'),
    ('Penjualan', 'Shift open + keranjang → konfirmasi checkout → nota, stok keluar, AR, pendapatan/HPP; cash, transfer, dan credit adalah alternatif.'),
    ('Piutang', 'Cash menambah laci langsung; transfer pending → admin verifikasi ATAU tolak. Pending belum menambah bank atau paid invoice.'),
    ('Pengiriman', 'Sale fulfillment delivery → SJ prepared → dispatch sent → konfirmasi received; tidak mengurangi stok untuk kedua kali.'),
    ('Biaya dan uang muka', 'Trip langsung ATAU advance open → realisasi + refund → settled; tidak wajib ada pada setiap pengiriman.'),
    ('Penutupan', 'Rekap per shift → hitung uang fisik → cocok closed ATAU selisih review → resolusi admin; bukan tutup periode akuntansi.'),
    ('Hilir pembukuan', 'Jurnal transaksi → buku jurnal serta laba rugi / neraca / arus kas yang dibaca independen, bukan rangkaian laporan wajib.'),
)


def digest(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def encoded(value: object) -> bytes:
    return (json.dumps(value, ensure_ascii=False, indent=2) + '\n').encode('utf-8')


def read_json(path: Path) -> dict:
    return json.loads(path.read_text(encoding='utf-8-sig'))


def safe_file(raw: str, *, doc_only: bool = False) -> Path:
    if not isinstance(raw, str) or '\\' in raw or ':' in raw or raw.startswith('/'):
        raise ValueError(f'Path relatif tidak sah: {raw!r}')
    parts = raw.split('/')
    if any(part in ('', '.', '..') for part in parts):
        raise ValueError(f'Path relatif tidak sah: {raw!r}')
    path = ROOT.joinpath(*parts)
    if not path.resolve().is_relative_to(OUT if doc_only else ROOT):
        raise ValueError(f'Path keluar scope: {raw}')
    for item in (path, *path.parents):
        if item == ROOT:
            break
        if item.is_symlink() or (item.exists() and item.is_junction()):
            raise ValueError(f'Tautan/junction tidak diizinkan: {raw}')
    if any(part.lower().startswith('.env') for part in parts):
        raise ValueError('Berkas environment tidak diizinkan')
    return path


def hashes(paths: list[Path]) -> dict[str, str]:
    return {p.relative_to(ROOT).as_posix(): digest(p.read_bytes()) for p in sorted(paths)}


def sources() -> dict[str, str]:
    paths = []
    for folder, suffixes in SOURCE_GROUPS.items():
        start = ROOT / folder
        if not start.exists():
            continue
        safe_file(folder)
        for current, dirs, files in os.walk(start, followlinks=False):
            dirs[:] = sorted(d for d in dirs if not (Path(current) / d).is_symlink()
                             and not (Path(current) / d).is_junction())
            for name in sorted(files):
                path = Path(current) / name
                if path.suffix in suffixes:
                    paths.append(safe_file(path.relative_to(ROOT).as_posix()))
    paths.extend(safe_file(name) for name in EXTRA_SOURCES if (ROOT / name).exists())
    return hashes(paths)


def inputs() -> dict[str, str]:
    files = [OUT / 'sinkron.py', CANONICAL, OUT / 'sumber/lintas-modul.json']
    files.extend((OUT / 'sumber/modul').glob('*.json'))
    return hashes(files)


def difference(old: dict, new: dict) -> list[str]:
    return [f'{"BARU" if key not in old else "HILANG" if key not in new else "BERUBAH"} {key}'
            for key in sorted(old.keys() | new.keys()) if old.get(key) != new.get(key)]


def renderer():
    spec = importlib.util.spec_from_file_location('tb01_documentation_renderer', CANONICAL)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def load_models():
    manifest = read_json(OUT / 'sumber/lintas-modul.json')
    packages = []
    seen = set()
    proof_count = 0
    for slug in manifest['order']:
        if not re.fullmatch('[0-9]{2}-[a-z-]+', slug):
            raise ValueError(f'Nama modul tidak sah: {slug}')
        package = read_json(OUT / f'sumber/modul/{slug}.json')
        # Accept research-package multi-location evidence; emit canonical v2 models.
        for step in package['steps']:
            if step['id'] in seen:
                raise ValueError(f'ID ganda: {step["id"]}')
            seen.add(step['id'])
            ref = step['evidence']
            if isinstance(ref, list):
                ref = ref[0]
            match = re.fullmatch(r'(.+):([1-9][0-9]*)(?:-[1-9][0-9]*)?', ref)
            if not match:
                raise ValueError(f'Bukti tidak sah: {ref}')
            step['evidence'] = f'{match[1]}:{match[2]}'
            step['tables'] = {
                table: ','.join(SHORT_OPS.get(op.strip(), op.strip()) for op in ops.split(','))
                for table, ops in step['tables'].items()
            }
        local_ids = {s['id'] for s in package['steps']}
        for proof in package['proofs']:
            if proof['step_id'] not in local_ids:
                raise ValueError(f'Bukti bukan milik langkah: {proof}')
            excerpt(proof)
            proof_count += 1
        without_proof = local_ids - {p['step_id'] for p in package['proofs']}
        if without_proof:
            raise ValueError(f'Langkah tanpa cuplikan: {sorted(without_proof)}')
        packages.append((slug, package))
    if set(p.stem for p in (OUT / 'sumber/modul').glob('*.json')) != set(manifest['order']):
        raise ValueError('Ada modul belum masuk urutan; telaah sebelum build')
    combined = {
        'title': manifest['title'],
        'steps': [s for _, p in packages for s in p['steps']],
        'edges': [e for _, p in packages for e in p['edges']] + manifest['edges'],
        'unknowns': manifest['unknowns'] + [f'{slug}: {u}' for slug, p in packages for u in p['unknowns']],
    }
    tool = renderer()
    tool.validate(combined, ROOT)
    for _, package in packages:
        tool.validate(canonical_model(package), ROOT)
    return manifest, packages, combined, tool, proof_count


def canonical_model(package):
    return {k: package[k] for k in ('title', 'steps', 'edges', 'unknowns')}


def excerpt(proof: dict) -> list[str]:
    path = safe_file(proof['path'])
    allowed = path.relative_to(ROOT).parts[0] in ('app', 'routes', 'resources', 'bootstrap', 'config')
    if not allowed or path.suffix not in ('.php', '.js', '.mjs', '.css'):
        raise ValueError(f'Sumber bukti di luar cakupan: {proof["path"]}')
    lines = path.read_text(encoding='utf-8-sig').splitlines()
    start, end = proof['start'], proof['end']
    if type(start) is not int or type(end) is not int or not 1 <= start <= end <= len(lines):
        raise ValueError(f'Rentang bukti tidak ada: {proof}')
    selected = lines[start - 1:end]
    if re.search(r'[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}|-----BEGIN .*PRIVATE KEY-----', '\n'.join(selected)):
        raise ValueError(f'Identitas/rahasia tidak dikutip: {proof["path"]}:{start}-{end}')
    return [f'{n:4} | {line}' for n, line in enumerate(selected, start)]


def proof_markdown(packages) -> bytes:
    lines = ['# Bukti kode langsung', '', INTRO, '',
             'Nomor di kiri adalah baris sumber asli, termasuk baris kosong. Jangan edit keluaran ini.', '']
    for slug, package in packages:
        lines.extend([f'## {slug} — {package["title"]}', ''])
        for step in package['steps']:
            lines.extend([f'### {step["id"]} — {step["label"]}', '', step['after'], ''])
            for proof in package['proofs']:
                if proof['step_id'] == step['id']:
                    lines.extend([f'{proof["label"]} — `{proof["path"]}:{proof["start"]}-{proof["end"]}`', '',
                                  '```text', *excerpt(proof), '```', ''])
    return ('\n'.join(lines) + '\n').encode('utf-8')


def write_word(path: Path, manifest, packages, combined, stamp):
    try:
        from docx import Document
        from docx.enum.text import WD_ALIGN_PARAGRAPH
        from docx.oxml import OxmlElement
        from docx.oxml.ns import qn
        from docx.shared import Cm, Pt, RGBColor
    except ImportError as exc:
        raise RuntimeError('Ekspor Word memerlukan python-docx di environment alat dokumentasi, bukan dependensi aplikasi.') from exc
    doc = Document()
    sec = doc.sections[0]
    sec.page_width, sec.page_height = Cm(21), Cm(29.7)
    sec.top_margin, sec.bottom_margin = Cm(1.8), Cm(1.8)
    sec.left_margin, sec.right_margin = Cm(1.9), Cm(1.9)
    for name in ('Normal', 'Body Text', 'List Bullet'):
        style = doc.styles[name]
        style.font.name, style.font.size = 'Calibri', Pt(10)
        style.paragraph_format.space_after = Pt(5)
    for name, size in [('Title', 30), ('Heading 1', 20), ('Heading 2', 14), ('Heading 3', 11)]:
        style = doc.styles[name]
        style.font.name, style.font.size = 'Calibri', Pt(size)
        style.font.color.rgb = RGBColor.from_string('14532D')
    doc.core_properties.title = manifest['title']
    doc.core_properties.author = 'Dokumentasi sumber tb01'
    doc.core_properties.subject = 'Tampilan working tree; bukan spesifikasi yang mengatur kode'
    sec.header.paragraphs[0].text = 'tb01  /  peta alur hidup  /  sumber → tampilan'
    footer = sec.footer.paragraphs[0]
    footer.alignment = WD_ALIGN_PARAGRAPH.RIGHT
    footer.add_run('Kode menang bila berbeda  |  halaman ')
    field = OxmlElement('w:fldSimple')
    field.set(qn('w:instr'), 'PAGE')
    footer._p.append(field)
    doc.add_paragraph('peta alur hidup', 'Subtitle')
    doc.add_paragraph('Kasir Bangunan', 'Title')
    doc.add_paragraph('dari hulu ke hilir, dengan bukti kode', 'Subtitle')
    doc.add_paragraph(INTRO)
    doc.add_paragraph(f'Dibangun {stamp["built_at"]}. Commit dasar {stamp["base_commit"]}.')
    doc.add_paragraph(f'Identitas sumber working tree: SHA-256 {stamp["source_digest"]}')
    doc.add_paragraph(f'{len(packages)} modul · {len(combined["steps"])} langkah · {len(combined["edges"])} panah bersyarat')
    doc.add_heading('cara membaca', 1)
    doc.add_paragraph('Mulai dari ringkasan berikut, lalu modul sesuai kebutuhan. Setiap langkah memuat syarat dan hasil bersyarat; panah bukan selalu urutan wajib. Bukti adalah cuplikan file asli dengan nomor baris, bukan rancangan kode.')
    for slug, package in packages:
        doc.add_paragraph(f'{slug} — {len(package["steps"])} langkah', 'List Bullet')
    doc.add_paragraph('Jalankan python docx/sinkron.py --check sebelum mengandalkan dokumen. Setelah kode berubah, cek harus gagal sampai peta ditelaah dan dibangun ulang. Tidak ada sinkron semantik otomatis atau pemantau latar.')
    doc.add_heading('ringkasan hulu sampai hilir', 1)
    for label, text in MAIN_FLOW:
        doc.add_heading(label, 3)
        doc.add_paragraph(text)
    doc.add_heading('kejanggalan sumber yang dicatat, bukan diperbaiki', 1)
    for observation in manifest['observations']:
        doc.add_heading(observation['title'], 3)
        doc.add_paragraph(observation['description'])
        doc.add_paragraph('Bukti: ' + observation['evidence'])
    for slug, package in packages:
        doc.add_page_break()
        doc.add_heading(slug + ' / ' + package['title'], 1)
        doc.add_heading('panah dan syarat', 2)
        for edge in package['edges']:
            doc.add_paragraph(f'{edge["from"]} → {edge["to"]}: {edge["label"]}', 'List Bullet')
        for step in package['steps']:
            doc.add_heading(step['id'] + ' / ' + step['label'], 2)
            doc.add_paragraph('Pelaku: ' + step['actor'])
            doc.add_paragraph('Pemicu / syarat: ' + step['before'])
            doc.add_paragraph('Hasil / gagal: ' + step['after'])
            doc.add_paragraph('Bukti utama: ' + step['evidence'])
            doc.add_paragraph('Operasi data: ' + ('; '.join(f'{t}: {ops}' for t, ops in sorted(step['tables'].items())) or 'Tidak ada write/read tabel yang dipetakan pada langkah UI ini.'))
            for proof in package['proofs']:
                if proof['step_id'] != step['id']:
                    continue
                doc.add_heading(proof['label'], 3)
                doc.add_paragraph(f'{proof["path"]}:{proof["start"]}-{proof["end"]}')
                para = doc.add_paragraph()
                para.paragraph_format.space_after = Pt(8)
                para.paragraph_format.line_spacing = 1.05
                shading = OxmlElement('w:shd')
                shading.set(qn('w:fill'), 'F1F5F2')
                para._p.get_or_add_pPr().append(shading)
                run = para.add_run('\n'.join(excerpt(proof)))
                run.font.name, run.font.size = 'Consolas', Pt(7.3)
        doc.add_heading('batas modul ini', 2)
        for unknown in package['unknowns']:
            doc.add_paragraph(unknown, 'List Bullet')
    doc.add_page_break()
    doc.add_heading('hubungan lintas modul', 1)
    for edge in manifest['edges']:
        doc.add_paragraph(f'{edge["from"]} → {edge["to"]}: {edge["label"]}', 'List Bullet')
    doc.add_heading('indeks operasi data', 1)
    uses = defaultdict(list)
    for step in combined['steps']:
        for table, ops in step['tables'].items():
            uses[table].append(f'{step["id"]} ({ops})')
    for table, rows in sorted(uses.items()):
        doc.add_heading(table, 3)
        doc.add_paragraph('; '.join(rows))
    doc.add_heading('batas keseluruhan dan sinkronisasi', 1)
    for unknown in manifest['unknowns']:
        doc.add_paragraph(unknown, 'List Bullet')
    doc.save(path)
    # Read back the generated package: all steps and all numbered excerpts must exist.
    reread = Document(path)
    text = '\n'.join(p.text for p in reread.paragraphs)
    for step in combined['steps']:
        if step['id'] + ' / ' + step['label'] not in text:
            raise ValueError(f'Langkah hilang dari Word: {step["id"]}')
    for _, package in packages:
        for proof in package['proofs']:
            if '\n'.join(excerpt(proof)) not in text:
                raise ValueError(f'Kutipan berubah/hilang dari Word: {proof}')


def emit(path: Path, data: bytes, previous):
    relative = path.relative_to(ROOT).as_posix()
    if path.exists():
        old = (previous or {}).get('outputs', {}).get(relative)
        if old is None or digest(path.read_bytes()) != old:
            raise ValueError(f'Tidak menimpa berkas asing/hasil edit manual: {relative}')
    path.parent.mkdir(parents=True, exist_ok=True)
    with tempfile.NamedTemporaryFile(dir=path.parent, suffix='.tmp', delete=False) as temp:
        temp.write(data)
        temp_path = Path(temp.name)
    os.replace(temp_path, path)


def build(reviewed: bool):
    previous = read_json(BASELINE) if BASELINE.exists() else None
    current = sources()
    input_snapshot = inputs()
    if previous:
        drift = difference(previous['sources'], current)
        if drift and not reviewed:
            raise ValueError('SUMBER BERUBAH; telusuri ulang narasi/guard/panah, lalu --build --accept-reviewed-source:\n' + '\n'.join(drift))
    manifest, packages, combined, tool, proof_count = load_models()
    commit = subprocess.run(['git', 'rev-parse', 'HEAD'], cwd=ROOT, capture_output=True, text=True)
    stamp = {
        'built_at': datetime.now(timezone.utc).isoformat(timespec='seconds'),
        'base_commit': commit.stdout.strip() if commit.returncode == 0 else 'tidak tersedia',
        'source_digest': digest(encoded(current)),
    }
    payloads = {
        OUT / 'model.json': encoded(combined),
        OUT / 'ALUR.md': tool.render(combined),
        OUT / 'BUKTI-KODE.md': proof_markdown(packages),
    }
    for slug, package in packages:
        payloads[OUT / f'modul/{slug}.md'] = tool.render(canonical_model(package))
    with tempfile.TemporaryDirectory(prefix='tb01-document-', dir=os.environ.get('TMPDIR')) as temp:
        word = Path(temp) / 'ALUR-SISTEM.docx'
        write_word(word, manifest, packages, combined, stamp)
        payloads[OUT / word.name] = word.read_bytes()
    if sources() != current or inputs() != input_snapshot:
        raise ValueError('Sumber/resep berubah selama build; tidak menerbitkan hasil campuran.')
    # Refuse overwriting edited outputs BEFORE publishing any generated artifact.
    for path in payloads:
        if path.exists():
            expected = (previous or {}).get('outputs', {}).get(path.relative_to(ROOT).as_posix())
            if expected is None or digest(path.read_bytes()) != expected:
                raise ValueError(f'Keluaran diedit manual/asing, tidak ditimpa: {path}')
    for path, payload in payloads.items():
        emit(path, payload, previous)
    baseline = {
        'format': 1, **stamp,
        'counts': {'modules': len(packages), 'steps': len(combined['steps']), 'edges': len(combined['edges']),
                   'proofs': proof_count, 'source_files': len(current)},
        'scope': {k: sorted(v) for k, v in SOURCE_GROUPS.items()},
        'extra_sources': list(EXTRA_SOURCES), 'sources': current,
        'inputs': input_snapshot, 'outputs': hashes(list(payloads)),
    }
    # Baseline is a generated receipt, not application data.
    BASELINE.write_bytes(encoded(baseline))
    print(json.dumps({'built': True, **baseline['counts'], 'document': 'docx/ALUR-SISTEM.docx'}, ensure_ascii=False))
    return check()


def check():
    if not BASELINE.exists():
        print('BELUM ADA BASELINE: build dahulu setelah sumber ditelaah.')
        return 1
    baseline = read_json(BASELINE)
    issues = ['SUMBER ' + item for item in difference(baseline['sources'], sources())]
    issues += ['RESEP ' + item for item in difference(baseline['inputs'], inputs())]
    for relative, expected in baseline['outputs'].items():
        path = safe_file(relative, doc_only=True)
        if not path.is_file():
            issues.append('KELUARAN HILANG ' + relative)
        elif digest(path.read_bytes()) != expected:
            issues.append('KELUARAN BERUBAH ' + relative)
    # Semantic model validity/location checks, not only hashes.
    _, packages, combined, tool, _ = load_models()
    actual_model = safe_file('docx/model.json', doc_only=True)
    if actual_model.exists() and actual_model.read_bytes() != encoded(combined):
        issues.append('MODEL tidak sesuai resep terkini')
    expected_maps = [(OUT / 'ALUR.md', combined)]
    expected_maps.extend((OUT / f'modul/{slug}.md', canonical_model(p)) for slug, p in packages)
    for path, model in expected_maps:
        if path.exists() and path.read_bytes() != tool.render(model):
            issues.append('RENDER tidak sesuai model ' + path.relative_to(ROOT).as_posix())
    if issues:
        print('TIDAK SINKRON\n' + '\n'.join(issues))
        return 1
    print(json.dumps({'synchronized': True, **baseline['counts'], 'source_digest': baseline['source_digest'],
                      'note': 'Identitas berkas cocok; bukan pembuktian runtime atau seluruh semantik.'}, ensure_ascii=False))
    return 0


def main():
    cli = argparse.ArgumentParser(description=__doc__)
    modes = cli.add_mutually_exclusive_group(required=True)
    modes.add_argument('--check', action='store_true')
    modes.add_argument('--build', action='store_true')
    cli.add_argument('--accept-reviewed-source', action='store_true', help='HANYA sesudah telusur ulang sumber, guard, status dan panah.')
    args = cli.parse_args()
    if args.accept_reviewed_source and not args.build:
        cli.error('--accept-reviewed-source hanya dengan --build')
    try:
        return build(args.accept_reviewed_source) if args.build else check()
    except (ValueError, OSError, KeyError, RuntimeError) as exc:
        print(str(exc), file=sys.stderr)
        return 2


if __name__ == '__main__':
    sys.exit(main())
