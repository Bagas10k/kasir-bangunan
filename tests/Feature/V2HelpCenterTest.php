<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\HelpText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class V2HelpCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Help Center fixtures require isolated SQLite :memory:.');
        }
    }

    public function test_help_requires_a_signed_in_active_workspace_user(): void
    {
        $this->get(route('help.index'))->assertRedirect(route('login'));
        $this->get(route('v2.help'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('help.index'))->assertForbidden();
    }

    public function test_cashier_sees_only_permitted_articles_in_navigation_search_and_direct_links(): void
    {
        config(['v2.enabled' => true]);
        $this->actingAs($this->workspaceUser('cashier'));
        $this->get(route('help.index'))->assertOk()
            ->assertSee('Operasional')
            ->assertDontSee('POS V2')
            ->assertDontSee('Catat biaya operasional')
            ->assertDontSee('Catat barang masuk');
        $this->get(route('help.index', ['q' => 'stok opname']))->assertOk()
            ->assertDontSee(route('help.index', 'stok-opname'))
            ->assertDontSee('Cocokkan stok dengan hasil hitung');
        $this->get(route('help.index', 'penerimaan'))->assertNotFound();
        $this->get(route('help.index', 'pengeluaran'))->assertNotFound();
        $this->get(route('pos.index'))->assertOk()
            ->assertSee('data-help-base="'.route('help.index').'"', false)
            ->assertSee('href="'.route('help.index').'" target="_blank"', false);
    }

    public function test_admin_can_read_admin_guides_and_open_the_exact_workspace_panel(): void
    {
        config(['v2.enabled' => true]);
        $this->actingAs($this->workspaceUser('admin'));
        $this->get(route('help.index', 'pengeluaran'))->assertOk()
            ->assertSee('Catat biaya operasional')
            ->assertDontSee('POS V2')
            ->assertSee('id="langkah"', false)
            ->assertSee('id="kendala"', false)
            ->assertSee('href="'.route('pos.index').'#pengeluaran"', false)
            ->assertSee('target="_blank"', false);

        config(['v2.enabled' => false]);
        $this->get(route('help.index', 'kasir'))->assertOk()
            ->assertSee('href="'.route('v2.index').'#kasir"', false);
    }

    public function test_search_reads_question_answers_accepts_common_terms_and_handles_empty_results(): void
    {
        $this->actingAs($this->workspaceUser('cashier'));
        $this->get(route('help.index', ['q' => 'koneksi putus']))
            ->assertOk()->assertSee('1 panduan')->assertSee(route('help.index', 'kasir'));
        $this->get(route('help.index', ['q' => 'cara shift']))
            ->assertOk()->assertSee(route('help.index', 'shift'))->assertDontSee('0 panduan');
        $this->get(route('help.index', ['q' => 'tidakditemukan123']))
            ->assertOk()->assertSee('0 panduan')->assertSee('Coba kata yang lebih pendek');
        $this->get(route('help.index', ['q' => ['not-a-string']]))->assertOk();
        $this->get(route('help.index', 'artikel-tidak-ada'))->assertNotFound();
    }

    public function test_articles_in_the_same_module_are_local_anchors_and_other_modules_keep_separate_links(): void
    {
        $this->actingAs($this->workspaceUser('cashier'));

        $this->get(route('help.index', 'kasir'))->assertOk()
            ->assertSeeInOrder(['id="panduan-kasir"', 'id="panduan-shift"'], false)
            ->assertSee('href="#panduan-shift"', false)
            ->assertSee('id="langkah-shift"', false)
            ->assertSee('href="'.route('help.index', 'mulai').'"', false)
            ->assertDontSee('id="panduan-mulai"', false)
            ->assertDontSee('id="panduan-tutup-kas"', false)
            ->assertDontSee('href="#panduan-tutup-kas"', false);

        $this->get(route('help.index', 'shift'))->assertOk()
            ->assertSeeInOrder(['id="panduan-shift"', 'id="langkah"', 'id="panduan-kasir"'], false)
            ->assertSeeInOrder(['href="#panduan-shift" data-module-article', 'href="#panduan-kasir" data-module-article'], false)
            ->assertDontSee('aria-label="Di artikel ini"', false);
    }

    public function test_legacy_help_links_redirect_without_bypassing_article_permissions(): void
    {
        config(['v2.enabled' => true]);
        $this->actingAs($this->workspaceUser('cashier'));

        $this->get('/v2/panduan?q=bukti+transfer')
            ->assertRedirect('/panduan?q=bukti%20transfer');
        $this->get('/v2/panduan/kasir')->assertRedirect('/panduan/kasir');
        $this->followingRedirects()->get('/v2/panduan/pengeluaran')->assertNotFound();
    }

    public function test_home_shows_only_the_available_modules_and_counts_for_each_role(): void
    {
        $this->actingAs($this->workspaceUser('cashier'))->get(route('help.index'))
            ->assertOk()
            ->assertSee('Jelajahi modul')
            ->assertSee('13 panduan dalam 2 modul')
            ->assertDontSee('<span>Master data</span>', false)
            ->assertDontSee('<strong>Master data</strong>', false)
            ->assertDontSee('Koreksi saldo Kas atau Bank');

        $this->actingAs($this->workspaceUser('admin'))->get(route('help.index'))
            ->assertOk()
            ->assertSee('41 panduan dalam 5 modul')
            ->assertSeeInOrder(['Mulai di sini', 'Operasional', 'Stok &amp; pembelian', 'Master data', 'Keuangan'], false);
    }

    public function test_new_workflows_are_searchable_and_open_the_relevant_feature_without_expanding_access(): void
    {
        config(['v2.enabled' => true]);
        $this->actingAs($this->workspaceUser('cashier'));
        $this->get(route('help.index', ['q' => 'lanjutkan transaksi']))->assertOk()
            ->assertSee(route('help.index', 'tahan-transaksi'));
        $this->get(route('help.index', ['q' => 'impor excel']))->assertOk()->assertSee('0 panduan');

        foreach ([
            'tahan-transaksi' => 'kasir',
            'harga-kontraktor' => 'kasir',
            'bayar-supplier' => 'tagihan',
            'sumber-dana-supplier' => 'tagihan',
            'transfer-ditolak' => 'persetujuan',
        ] as $article => $task) {
            $this->get(route('help.index', $article))->assertOk()
                ->assertSee('href="'.route('pos.index').'#'.$task.'"', false);
        }

        $restricted = [
            'ubah-draf-penerimaan' => 'penerimaan',
            'impor-excel' => 'master-product',
            'aktifkan-data' => 'master-product',
            'koreksi-saldo-kas-bank' => 'jurnal',
        ];
        foreach (array_keys($restricted) as $article) {
            $this->get(route('help.index', $article))->assertNotFound();
        }

        $this->actingAs($this->workspaceUser('admin'));
        $this->get(route('help.index', ['q' => 'impor excel']))->assertOk()
            ->assertSee(route('help.index', 'impor-excel'));
        foreach ($restricted as $article => $task) {
            $this->get(route('help.index', $article))->assertOk()
                ->assertSee('href="'.route('pos.index').'#'.$task.'"', false);
        }
    }

    public function test_illustrations_show_a_single_focus_and_keep_the_real_feature_link_separate(): void
    {
        config(['v2.enabled' => true]);
        $this->actingAs($this->workspaceUser('cashier'))->get(route('help.index', 'tahan-transaksi'))
            ->assertOk()
            ->assertSeeInOrder([
                'id="panduan-tahan-transaksi"',
                'href="'.route('pos.index').'#kasir"',
                'class="hc-mock hc-mock--footer-start" aria-hidden="true"',
                '<strong class="hc-mock-focus">Tahan transaksi</strong>',
                'Sorotan: Tahan transaksi.',
                'Ilustrasi layar, bukan tombol aktif.',
            ], false)
            ->assertDontSee('Sorotan: Impor Excel.');

        $this->actingAs($this->workspaceUser('admin'))->get(route('help.index', 'impor-excel'))
            ->assertOk()->assertSee('Sorotan: Impor Excel.');
    }

    public function test_interactive_tools_are_progressive_and_the_suggestion_index_respects_roles(): void
    {
        $this->actingAs($this->workspaceUser('cashier'))->get(route('help.index', 'kasir'))
            ->assertOk()
            ->assertSee('id="hc-suggest"', false)
            ->assertDontSee('id="hc-index"', false)
            ->assertSee('data-print-article hidden', false)
            ->assertDontSee('data-copy-link', false)
            ->assertDontSee('data-step-progress', false)
            ->assertSeeInOrder(['class="hc-nav"', 'id="hc-search"', 'class="hc-nav-modules"'], false)
            ->assertSee('<summary>Tombol bayar tidak bisa dipakai?</summary>', false)
            ->assertDontSee('Catat biaya operasional');

        $this->getJson(route('help.index', ['q' => 'biaya operasional']))->assertOk()
            ->assertJsonMissing(['url' => route('help.index', 'pengeluaran')]);
        $this->actingAs($this->workspaceUser('admin'))
            ->getJson(route('help.index', ['q' => 'biaya operasional']))->assertOk()
            ->assertJsonPath('results.0.url', route('help.index', 'pengeluaran'));
    }

    public function test_text_markers_link_related_guides_only_when_the_role_may_open_them(): void
    {
        $this->actingAs($this->workspaceUser('cashier'));
        $page = $this->get(route('help.index', 'kasir'))->assertOk()
            ->assertSee('<a class="hc-ref" href="#panduan-tahan-transaksi"', false)
            ->assertSee('<mark class="hc-important">Jangan membuat nota baru', false)
            ->assertSee('<em class="hc-term">', false)
            ->assertSee('<span class="hc-optional">Periksa ongkir', false)
            ->assertDontSee('!!Jangan', false)
            ->assertDontSee('((Periksa', false);
        $page->assertDontSee('href="'.route('help.index', 'retur-penjualan').'"', false)
            ->assertSee('sebagai retur penjualan');

        $this->actingAs($this->workspaceUser('admin'))->get(route('help.index', 'kasir'))->assertOk()
            ->assertSee('<a class="hc-ref" href="'.route('help.index', 'retur-penjualan').'"', false);

        $html = (string) HelpText::render('<b>x</b> !!<i>y</i>!!', 'kasir', fn (): ?string => null, []);
        $this->assertSame('&lt;b&gt;x&lt;/b&gt; <mark class="hc-important">&lt;i&gt;y&lt;/i&gt;</mark>', $html);
        $this->assertSame('a b c', HelpText::plain('a !!b!! ((c))'));
    }

    public function test_search_matches_word_by_word_with_affixes_and_synonyms(): void
    {
        $this->actingAs($this->workspaceUser('cashier'));

        $results = fn (string $query): array => array_column($this->getJson(route('help.index', ['q' => $query]))->assertOk()->json('results'), 'url');

        $this->assertContains(route('help.index', 'tagihan'), $results('cara bayar tagihan'));
        $this->assertSame(route('help.index', 'bayar-supplier'), $results('membayar supplier')[0]);
        $this->assertContains(route('help.index', 'riwayat-penjualan'), $results('cetak ulang struk'));
        $this->assertSame([], $results('zzqxv'));
        $this->assertLessThanOrEqual(6, count($results('kas')));

        $this->get(route('help.index', ['q' => 'gimana cara pelunasan hutang']))->assertOk()
            ->assertDontSee('0 panduan')
            ->assertSee(route('help.index', 'tagihan'));
    }

    public function test_on_page_contents_lists_sections_for_each_module_article(): void
    {
        $this->actingAs($this->workspaceUser('cashier'))->get(route('help.index', 'kasir'))
            ->assertOk()
            ->assertSeeInOrder([
                'class="hc-onpage"',
                'data-onpage-for="panduan-kasir"',
                'href="#langkah"', 'href="#kendala"', 'href="#terkait"',
                'data-onpage-for="panduan-tahan-transaksi"',
                'href="#langkah-tahan-transaksi"',
            ], false)
            ->assertSee('id="terkait"', false);

        $this->get(route('help.index'))->assertOk()->assertDontSee('class="hc-onpage"', false);
    }

    public function test_every_article_has_an_explanation_that_is_rendered_and_searchable(): void
    {
        foreach (config('help-center.groups') as $entries) {
            foreach (array_keys($entries) as $slug) {
                $this->assertNotEmpty(config('help-center.details.'.$slug), "Missing explanation: {$slug}");
                $this->assertCount(3, config('help-center.illustrations.'.$slug, []), "Missing illustration: {$slug}");
            }
        }

        $this->actingAs($this->workspaceUser('cashier'));
        $this->get(route('help.index', 'tahan-transaksi'))->assertOk()
            ->assertSeeInOrder(['id="panduan-tahan-transaksi"', 'id="penjelasan"', 'Hingga 10 keranjang bisa ditahan', 'id="langkah"'], false)
            ->assertSee('href="#penjelasan"', false);
        $this->get(route('help.index', ['q' => 'rata-rata tertimbang']))->assertOk()->assertSee('0 panduan');
        $this->get(route('help.index', ['q' => 'dibersihkan']))->assertOk()
            ->assertSee(route('help.index', 'tahan-transaksi'));
    }

    private function workspaceUser(string $role): User
    {
        $user = User::factory()->create();
        DB::table('v2_user_roles')->insert(['user_id' => $user->id, 'role' => $role]);

        return $user;
    }
}
