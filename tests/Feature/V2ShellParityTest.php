<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\V2\DemoData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Halaman lama (jurnal, laporan, COA) harus memakai cangkang V2 yang sama dengan workspace:
 * sidebar, menu, tema, dan perilaku sidebar. Tanpa ini, klik menu Keuangan terasa pindah aplikasi.
 */
class V2ShellParityTest extends TestCase
{
    use RefreshDatabase;

    private const LEGACY_ROUTES = [
        'coa.index', 'journals.index', 'reports.profit_loss', 'reports.balance_sheet', 'reports.cash_flow',
        'products.index', 'vehicles.index', 'expenses.index', 'ritase.index', 'receivables.index', 'opname.index', 'inventory.receipts',
    ];

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('RefreshDatabase is only permitted against isolated SQLite :memory:.');
        }
    }

    private function admin(): User
    {
        $user = User::factory()->create(['name' => 'Admin Sintetis Cangkang']);
        DB::table('v2_user_roles')->insert(['user_id' => $user->id, 'role' => 'admin', 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    /** Jurnal sintetis seimbang (hanya DB tes in-memory) agar fixture laporan Keuangan berisi angka. */
    private function seedPreviewJournals(): void
    {
        $names = ['1101' => 'Kas Toko (Tunai)', '1102' => 'Rekening Bank', '1103' => 'Piutang Usaha (Pelanggan/Kontraktor)', '1301' => 'Persediaan Material Bangunan', '2101' => 'Utang Usaha (Pemasok/Supplier)', '3101' => 'Modal Pemilik / Modal Awal', '4101' => 'Pendapatan Penjualan Material', '5101' => 'Beban Pokok Penjualan (HPP)', '6101' => 'Beban Operasional Toko', '6102' => 'Beban Listrik & Air'];
        $entries = [
            [20, 'opening', 'Saldo awal pembukuan', [['1101', '25000000', '0'], ['1102', '60000000', '0'], ['1301', '45000000', '0'], ['3101', '0', '130000000']]],
            [12, 'purchase', 'Pembelian semen dan besi dari pemasok', [['1301', '18500000', '0'], ['2101', '0', '18500000']]],
            [9, 'sale', 'Penjualan tunai material', [['1101', '7450000', '0'], ['4101', '0', '7450000'], ['5101', '5600000', '0'], ['1301', '0', '5600000']]],
            [6, 'sale', 'Penjualan transfer ke kontraktor', [['1102', '12800000', '0'], ['4101', '0', '12800000'], ['5101', '9900000', '0'], ['1301', '0', '9900000']]],
            [5, 'sale', 'Penjualan kredit (piutang)', [['1103', '4350000', '0'], ['4101', '0', '4350000'], ['5101', '3300000', '0'], ['1301', '0', '3300000']]],
            [4, 'expense', 'Bayar listrik dan air toko', [['6102', '1250000', '0'], ['1101', '0', '1250000']]],
            [3, 'payable', 'Pembayaran utang pemasok', [['2101', '10000000', '0'], ['1102', '0', '10000000']]],
            [2, 'expense', 'Upah bongkar muat harian', [['6101', '850000', '0'], ['1101', '0', '850000']]],
            [1, 'receivable', 'Pelunasan piutang pelanggan', [['1102', '2000000', '0'], ['1103', '0', '2000000']]],
        ];
        foreach ($entries as [$daysAgo, $type, $description, $lines]) {
            $id = DB::table('journal_entries')->insertGetId(['reference_type' => $type, 'reference_id' => null, 'description' => $description, 'transaction_date' => now()->subDays($daysAgo)->toDateString(), 'created_at' => now(), 'updated_at' => now()]);
            foreach ($lines as [$code, $debit, $credit]) {
                DB::table('journal_entry_lines')->insert(['journal_entry_id' => $id, 'account_code' => $code, 'account_name' => $names[$code], 'debit' => $debit, 'credit' => $credit, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    private function menuLabels(string $html): array
    {
        preg_match('/<nav class="nav sidebar-nav".*?<\/nav>/s', $html, $nav);
        preg_match_all('/<summary[^>]*>.*?<span>(.*?)<\/span><\/summary>/s', $nav[0] ?? '', $groups);
        preg_match_all('/<a [^>]*><span>(.*?)<\/span><\/a>/s', $nav[0] ?? '', $items);

        return [$groups[1], $items[1]];
    }

    public function test_legacy_pages_render_inside_the_same_shell_as_the_workspace(): void
    {
        config(['v2.enabled' => true]);
        $admin = $this->admin();
        if (getenv('V2_PREVIEW_DIR') && getenv('V2_PREVIEW_DEMO')) {
            app(DemoData::class)->seed($admin->id); // fixture tampilan berisi data sintetis
            $this->seedPreviewJournals();
        }
        $workspace = $this->actingAs($admin)->get(route('pos.index'))->assertOk()->getContent();
        [$workspaceGroups, $workspaceItems] = $this->menuLabels($workspace);
        $this->assertNotEmpty($workspaceItems);

        foreach (self::LEGACY_ROUTES as $name) {
            $response = $this->actingAs($admin)->get(route($name))->assertOk();
            $html = $response->getContent();
            $response->assertSee('<script id="numeric-input-guard">', false)
                ->assertSee('id="workspace-sidebar"', false)
                ->assertSee('id="sidebar-overlay"', false)
                ->assertSee('--ui-side:#0f2a1d', false)
                ->assertSee('rail-flyout', false)
                ->assertDontSee('id="app-sidebar"', false);
            $this->assertSame([$workspaceGroups, $workspaceItems], $this->menuLabels($html), "Menu di {$name} sama dengan workspace.");
            $this->assertSame(1, substr_count($html, 'class="nav sidebar-nav"'), "Satu sidebar di {$name}.");
            $this->assertStringContainsString('aria-current="page"', $html, "Menu aktif ditandai di {$name}.");

            if ($previewDir = getenv('V2_PREVIEW_DIR')) {
                file_put_contents($previewDir.'/legacy-'.str_replace('.', '-', $name).'.html', $html);
                // Isi panel Keuangan seperti yang dimuat workspace (?partial=1).
                if (in_array($name, ['coa.index', 'journals.index', 'reports.profit_loss', 'reports.balance_sheet', 'reports.cash_flow'], true)) {
                    file_put_contents($previewDir.'/partial-'.str_replace('.', '-', $name).'.html', $this->actingAs($admin)->get(route($name, ['partial' => 1]))->assertOk()->getContent());
                }
            }
        }
    }

    public function test_legacy_shell_is_unchanged_when_v2_is_disabled(): void
    {
        config(['v2.enabled' => false]);
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('coa.index'))->assertOk()->assertSee('id="app-sidebar"', false)->assertDontSee('id="workspace-sidebar"', false);
    }
}
