<?php

namespace App\Console\Commands;

use App\Services\V2\DemoData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class V2DemoData extends Command
{
    protected $signature = 'v2:demo-data {--remove : Hapus data contoh yang tercatat di manifest} {--admin= : ID user admin pelaksana}';

    protected $description = 'Tambah (atau hapus) data contoh master V2: satuan, kategori, supplier, pelanggan, karyawan, sopir, mobil, produk.';

    private const MANIFEST = 'v2-demo-data.json';

    public function handle(DemoData $demo): int
    {
        if (app()->isProduction()) {
            $this->error('Ditolak: data contoh tidak boleh dibuat di produksi.');

            return self::FAILURE;
        }
        $manifest = Storage::disk('local')->exists(self::MANIFEST)
            ? (json_decode(Storage::disk('local')->get(self::MANIFEST), true) ?: [])
            : [];

        if ($this->option('remove')) {
            $result = $demo->remove($manifest);
            Storage::disk('local')->delete(self::MANIFEST);
            $this->info("Data contoh dihapus: {$result['removed']} baris.");
            if ($result['kept'] !== []) {
                $this->warn('Dipertahankan karena sudah dipakai: '.implode(', ', $result['kept']));
            }

            return self::SUCCESS;
        }

        $admin = (int) ($this->option('admin') ?: DB::table('v2_user_roles')->where('role', 'admin')->orderBy('user_id')->value('user_id'));
        if ($admin <= 0) {
            $this->error('Tidak ada user admin V2. Jalankan dengan --admin=<id>.');

            return self::FAILURE;
        }

        $made = DB::transaction(fn (): array => $demo->seed($admin));
        foreach ($made as $group => $ids) {
            $manifest[$group] = array_values(array_unique(array_merge($manifest[$group] ?? [], $ids)));
        }
        Storage::disk('local')->put(self::MANIFEST, json_encode($manifest, JSON_PRETTY_PRINT));

        $this->table(['Data', 'Baru dibuat'], collect($made)->map(fn (array $ids, string $group): array => [$group, count($ids)])->values()->all());
        $this->info('Manifest: '.Storage::disk('local')->path(self::MANIFEST).'. Hapus kapan saja dengan: php artisan v2:demo-data --remove');

        return self::SUCCESS;
    }
}
