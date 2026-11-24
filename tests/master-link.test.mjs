import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const system = readFileSync(new URL('../resources/views/v2/ui-system.blade.php', import.meta.url), 'utf8');
const master = readFileSync(new URL('../resources/views/v2/master-page-item.blade.php', import.meta.url), 'utf8');
const staff = readFileSync(new URL('../resources/views/v2/master-staff.blade.php', import.meta.url), 'utf8');

test('nama supplier/mobil tidak menambahkan panah buatan CSS pada nama aksesibel', () => {
    assert.doesNotMatch(system, /\.ui-link::after/);
    const supplier = master.split('\n').find((line) => line.includes('data-insight="supplier"'));
    const vehicle = master.split('\n').find((line) => line.includes('data-insight="vehicle"'));
    assert.match(supplier, /aria-haspopup="dialog"/);
    assert.match(vehicle, /aria-haspopup="dialog"/);
});

test('keterangan karyawan/sopir menjelaskan arsip tidak menghapus riwayat pengiriman', () => {
    assert.match(staff, /Mengarsipkan karyawan atau sopir[^<]*riwayat pengiriman/);
});
