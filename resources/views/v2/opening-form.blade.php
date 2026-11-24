<section class="card" aria-labelledby="opening-heading">
    <h2 id="opening-heading">Saldo awal pembukuan</h2>
    @if ($openingState['status'] !== 'ready')
        <p><strong>{{ $openingState['message'] }}</strong></p>
        @if ($openingState['status'] === 'posted')
            <p class="note">{{ \Illuminate\Support\Carbon::parse($openingState['date'])->locale('id')->translatedFormat('j F Y') }} · Referensi: {{ $openingState['reference'] }}</p>
        @else
            <p class="note">Saldo awal hanya bisa diisi sebelum toko mulai mencatat transaksi, stok, atau tagihan.</p>
        @endif
        @if ($openingState['status'] === 'posted' || $openingState['field'] === 'journal')
            <a href="{{ route('journals.index') }}#adjustment-panel">Koreksi kas atau bank lewat Penyesuaian Saldo</a>
        @endif
    @else
        <p class="note" data-opening-card-status tabindex="-1">Siap diisi. Catat uang, stok, dan tagihan yang sudah ada sebelum toko mulai memakai sistem. Hanya bisa diposting sekali.</p>
        <form id="opening-form" method="post" action="{{ route('v2.openings.store') }}" data-preview-url="{{ route('v2.openings.preview') }}" novalidate>
            <div data-opening-edit>
                <div class="ob-grid">
                    <div class="field"><label for="ob-date">Tanggal mulai</label><input id="ob-date" type="date" name="date" value="{{ now()->toDateString() }}" required></div>
                    <div class="field"><label for="ob-ref">Referensi (opsional)</label><input id="ob-ref" name="reference" maxlength="50" placeholder="Contoh: Pembukaan toko"></div>
                    <div class="field"><label for="ob-cash">Kas tunai (Rp)</label><input id="ob-cash" name="cash" type="text" data-money inputmode="decimal" maxlength="30" placeholder="0"></div>
                    <div class="field"><label for="ob-bank">Saldo bank (Rp)</label><input id="ob-bank" name="bank" type="text" data-money inputmode="decimal" maxlength="30" placeholder="0"></div>
                </div>
                <p class="note">Kas atau bank yang kosong dihitung Rp 0.</p>
                @foreach ([
                    'inventory' => ['Stok awal', 'stok awal', 'Produk', $products],
                    'receivables' => ['Piutang pelanggan', 'piutang', 'Pelanggan', $customers],
                    'payables' => ['Utang supplier', 'utang', 'Supplier', $suppliers],
                ] as $group => [$title, $noun, $party, $choices])
                    <details class="ob-section" data-opening-group="{{ $group }}">
                        <summary>{{ $title }} <span class="note">(opsional)</span></summary>
                        <div data-opening-rows></div>
                        <template>
                            <div class="ob-grid ob-row">
                                <div class="field">
                                    <label>{{ $party }}</label>
                                    <select data-opening-field="{{ $group === 'inventory' ? 'product_id' : 'party_id' }}" required>
                                        <option value="">Pilih {{ strtolower($party) }}</option>
                                        @foreach ($choices as $choice)
                                            <option value="{{ $choice->id }}">{{ $choice->name }}{{ $group === 'inventory' ? ' · '.$choice->sku.' · '.$choice->unit : '' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @if ($group === 'inventory')
                                    <div class="field"><label>Jumlah</label><input type="text" inputmode="decimal" data-money data-money-scale="4" data-opening-field="quantity" maxlength="30" required></div>
                                    <div class="field"><label>Harga beli per satuan (Rp)</label><input type="text" data-money data-money-scale="4" data-opening-field="unit_cost" maxlength="30" required></div>
                                @else
                                    <div class="field"><label>Nomor tagihan</label><input type="text" data-opening-field="number" maxlength="64" required></div>
                                    <div class="field"><label>Nominal (Rp)</label><input type="text" data-money data-opening-field="amount" maxlength="30" required></div>
                                    <div class="field"><label>Jatuh tempo (opsional)</label><input type="date" data-opening-field="due_date"></div>
                                @endif
                                <button class="secondary" type="button" data-opening-remove>Hapus baris</button>
                            </div>
                        </template>
                        <button class="secondary" type="button" data-opening-add>Tambah {{ $noun }}</button>
                    </details>
                @endforeach
                <div class="ob-actions"><button type="submit" disabled>Periksa saldo awal</button></div>
            </div>
            <div data-opening-review hidden>
                <h3 tabindex="-1">Periksa saldo awal</h3>
                <p class="note">Belum diposting. Setelah diposting, saldo awal tidak bisa diubah.</p>
                <p class="note" data-opening-reference></p>
                <dl class="ob-summary">
                    @foreach (['cash' => 'Kas tunai', 'bank' => 'Bank', 'inventory' => 'Total nilai stok', 'receivables' => 'Total piutang', 'payables' => 'Total utang', 'equity' => 'Modal awal'] as $key => $label)
                        <div><dt>{{ $label }}</dt><dd data-opening-amount="{{ $key }}"></dd></div>
                    @endforeach
                </dl>
                <div class="ob-actions"><button class="secondary" type="button" data-opening-back>Ubah isian</button><button type="submit">Posting saldo awal</button></div>
            </div>
            <p data-error class="err" tabindex="-1" hidden></p>
            <p data-ok class="ok" role="status" tabindex="-1" hidden></p>
        </form>
    @endif
</section>
