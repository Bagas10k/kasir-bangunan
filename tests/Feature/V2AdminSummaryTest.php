<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\V2\AdminSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Angka halaman Pengawasan · Ringkasan: murni baca, data sintetis di SQLite :memory:. */
class V2AdminSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('V2 admin summary tests require isolated SQLite :memory:.');
        }
    }

    private function shift(User $owner, string $status, string $opening): int
    {
        return (int) DB::table('v2_shifts')->insertGetId([
            'user_id' => $owner->id, 'open_user_id' => $status === 'open' ? $owner->id : null, 'source' => 'admin', 'opening_cash' => $opening,
            'status' => $status, 'opened_at' => '2026-11-07 07:00:00', 'closed_at' => $status === 'open' ? null : '2026-11-07 17:00:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function movement(int $shift, string $direction, string $amount, int $ref): void
    {
        DB::table('v2_shift_movements')->insert([
            'shift_id' => $shift, 'direction' => $direction, 'kind' => $direction === 'in' ? 'sale' : 'supplier', 'amount' => $amount,
            'reference_type' => 'syn', 'reference_id' => $ref, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function product(string $sku, string $stock, string $min, bool $active = true): void
    {
        DB::table('products')->insert([
            'sku' => $sku, 'name' => 'Produk '.$sku, 'unit' => 'sak', 'category' => 'Umum', 'cost_price' => '1', 'selling_price' => '2',
            'stock_quantity' => $stock, 'min_stock' => $min, 'is_active' => $active, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_snapshot_reads_drawer_bank_overdue_and_stock_watch(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        // Laci shift terbuka = kas awal + masuk - keluar; shift tertutup tidak dihitung.
        $open1 = $this->shift($a, 'open', '100000.00');
        $open2 = $this->shift($b, 'open', '50000.50');
        $closed = $this->shift(User::factory()->create(), 'closed', '999999.00');
        $this->movement($open1, 'in', '25000.25', 1);
        $this->movement($open1, 'out', '5000.00', 2);
        $this->movement($open2, 'in', '0.25', 3);
        $this->movement($closed, 'in', '777.00', 4);

        // Saldo bank = debit - kredit akun 1102 di buku besar.
        $journal = DB::table('journal_entries')->insertGetId(['reference_type' => 'syn', 'description' => 'Sintetis', 'transaction_date' => '2026-11-07', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([['1102', '300000.00', '0'], ['1102', '0', '45000.10'], ['1101', '9999.00', '0']] as [$code, $debit, $credit]) {
            DB::table('journal_entry_lines')->insert(['journal_entry_id' => $journal, 'account_code' => $code, 'account_name' => 'Akun '.$code, 'debit' => $debit, 'credit' => $credit, 'created_at' => now(), 'updated_at' => now()]);
        }

        $party = (int) DB::table('v2_parties')->insertGetId(['kind' => 'customer', 'code' => 'SYN-C', 'name' => 'Pelanggan Sintetis', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $seq = 0;
        foreach ([['receivable', 'open', '2026-11-01'], ['receivable', 'open', '2026-11-07'], ['receivable', 'paid', '2026-10-01'], ['payable', 'open', '2026-10-01'], ['receivable', 'open', null]] as [$direction, $status, $due]) {
            DB::table('v2_invoices')->insert(['direction' => $direction, 'party_id' => $party, 'number' => 'SYN-INV-'.++$seq, 'amount' => '10.00', 'paid' => $status === 'paid' ? '10.00' : '0.00', 'status' => $status, 'due_date' => $due, 'created_at' => now(), 'updated_at' => now()]);
        }

        $this->product('LOW-A', '8', '10');      // rasio 0,8
        $this->product('OUT-B', '0', '5');       // habis
        $this->product('LOW-C', '1', '10');      // rasio 0,1
        $this->product('OK-D', '50', '10');      // aman, tidak tampil
        $this->product('OUT-E', '0', '0');       // habis, tanpa minimum
        $this->product('ARC-F', '0', '5', false); // arsip, tidak tampil

        $snapshot = app(AdminSummary::class)->snapshot('2026-11-07');

        $this->assertSame(['total' => '170001.00', 'count' => 2], $snapshot['drawer']);
        $this->assertSame('254999.90', $snapshot['bank']);
        $this->assertSame(1, $snapshot['receivable_overdue']);
        $this->assertSame(['Produk OUT-B', 'Produk OUT-E', 'Produk LOW-C', 'Produk LOW-A'], array_column($snapshot['stock_watch'], 'name'));
        $this->assertSame(['0', '0', '1', '8'], array_column($snapshot['stock_watch'], 'qty'));
        $this->assertNull($snapshot['unassigned']);
    }
}
