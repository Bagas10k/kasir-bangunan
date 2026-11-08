<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\V2\CustomerLookup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Kasir mengetik nama pembeli: yang ada disarankan, yang belum ada dibuat sekali saja.
 */
class V2CustomerLookupTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('RefreshDatabase is only permitted against isolated SQLite :memory:.');
        }
    }

    private function user(string $role, string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        DB::table('v2_user_roles')->insert(['user_id' => $user->id, 'role' => $role, 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    private function customer(string $code, string $name, bool $active = true): int
    {
        return DB::table('v2_parties')->insertGetId([
            'kind' => 'customer', 'code' => $code, 'name' => $name, 'is_active' => $active,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_search_suggests_active_customers_by_name_or_code(): void
    {
        $this->customer('CUS-0001', 'Toko Sinar Jaya');
        $this->customer('CUS-0002', 'Sinar Abadi Material');
        $this->customer('CUS-0003', 'Pelanggan Arsip', false);
        $lookup = app(CustomerLookup::class);

        $names = array_column($lookup->search('sinar'), 'name');
        $this->assertEqualsCanonicalizing(['Toko Sinar Jaya', 'Sinar Abadi Material'], $names);
        $this->assertSame([], $lookup->search('s'), 'Satu huruf belum dicari.');
        $this->assertSame(['Toko Sinar Jaya'], array_column($lookup->search('CUS-0001'), 'name'));
        $this->assertSame([], $lookup->search('Arsip'), 'Pelanggan yang diarsipkan tidak disarankan.');
    }

    public function test_resolve_reuses_existing_name_and_creates_only_once(): void
    {
        $cashier = $this->user('cashier', 'Kasir Sintetis Cari');
        $existing = $this->customer('CUS-0001', 'Toko Sinar Jaya');
        $lookup = app(CustomerLookup::class);

        $same = $lookup->resolve($cashier->id, '  toko   sinar jaya ');
        $this->assertSame($existing, $same['id'], 'Nama sama (beda huruf/spasi) memakai data yang ada.');
        $this->assertFalse($same['created']);

        $fresh = $lookup->resolve($cashier->id, 'Bapak Andi Proyek');
        $this->assertTrue($fresh['created']);
        $this->assertSame('CUS-0002', $fresh['code'], 'Kode lanjut dari yang terakhir.');

        $again = $lookup->resolve($cashier->id, 'bapak andi proyek');
        $this->assertSame($fresh['id'], $again['id'], 'Tidak membuat pelanggan kembar.');
        $this->assertSame(2, DB::table('v2_parties')->where('kind', 'customer')->count());
    }

    public function test_resolve_rejects_short_name_and_archived_customer(): void
    {
        $cashier = $this->user('cashier', 'Kasir Sintetis Tolak');
        $this->customer('CUS-0009', 'Pelanggan Arsip', false);
        $lookup = app(CustomerLookup::class);

        $this->expectExceptionMessage('minimal 2 huruf');
        $lookup->resolve($cashier->id, 'A');
    }

    public function test_archived_name_is_not_silently_revived(): void
    {
        $cashier = $this->user('cashier', 'Kasir Sintetis Arsip');
        $this->customer('CUS-0009', 'Pelanggan Arsip', false);

        $this->expectExceptionMessage('diarsipkan');
        app(CustomerLookup::class)->resolve($cashier->id, 'Pelanggan Arsip');
    }

    public function test_routes_are_limited_to_shop_staff(): void
    {
        config(['v2.enabled' => true]);
        $cashier = $this->user('cashier', 'Kasir Sintetis Rute');
        $this->customer('CUS-0001', 'Toko Sinar Jaya');

        $this->actingAs($cashier)->getJson(route('v2.customers.search', ['q' => 'sinar']))
            ->assertOk()->assertJsonPath('results.0.name', 'Toko Sinar Jaya');

        $this->actingAs($cashier)->postJson(route('v2.customers.resolve'), ['name' => 'Pembeli Baru Sintetis'])
            ->assertOk()->assertJsonPath('created', true);

        $this->actingAs($cashier)->postJson(route('v2.customers.resolve'), ['name' => 'A'])->assertStatus(422);

        $outsider = User::factory()->create(['name' => 'Tanpa Peran Sintetis']);
        $this->actingAs($outsider)->getJson(route('v2.customers.search', ['q' => 'sinar']))->assertForbidden();
    }

    public function test_cashier_screen_ships_the_typeahead(): void
    {
        config(['v2.enabled' => true]);
        $cashier = $this->user('cashier', 'Kasir Sintetis Layar');
        $html = $this->actingAs($cashier)->get(route('pos.index'))->assertOk()->getContent();

        $this->assertStringContainsString(route('v2.customers.search'), $html);
        $this->assertStringContainsString(route('v2.customers.resolve'), $html);
        $this->assertStringContainsString('pos-combo-input', $html);
        $this->assertStringContainsString('id="customer-id"', $html, 'Select asli tetap ada agar aturan kredit berlaku.');
    }
}
