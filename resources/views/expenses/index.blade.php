@extends(request()->boolean('partial') ? 'layouts.partial' : 'layouts.app')
@section('content')
@include('expenses.theme-styles')
@php
    $formatMoney = function ($value) {
        $decimal = \Brick\Math\BigDecimal::of((string) $value)->toScale(2, \Brick\Math\RoundingMode::HalfUp);
        $negative = $decimal->isNegative();
        [$whole, $fraction] = explode('.', ltrim((string) $decimal, '-'));
        $formatted = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole) . ',' . $fraction;
        return $negative ? '-' . $formatted : $formatted;
    };
    $accountNames = collect($expenseAccounts ?? [])->pluck('account_name', 'account_code');
    $selectedAccountCode = old('account_code', old('category'));
    $selectedSource = old('source', old('payment_method') === 'transfer' ? 'bank' : (old('payment_method') ?: 'cash'));
    $activeBankAccounts = collect($bankAccounts ?? []);
    if ($activeBankAccounts->isEmpty()) {
        $activeBankAccounts = collect([(object) ['account_code' => '1102', 'account_name' => 'Rekening Bank']]);
    }
    $selectedBankCode = old('bank_account_code', (string) $activeBankAccounts->first()->account_code);
    $bankNamesMap = $bankAccountNames ?? $activeBankAccounts->pluck('account_name', 'account_code')->all();
    $bankBalancesMap = $bankBalances ?? ['1102' => $bankBalance ?? '0.00'];
@endphp
<div class="expenses-page" data-expense-root data-refresh-url="{{ route('expenses.index') }}">
    @if(session('success'))
        <div class="exp-alert exp-alert--ok" role="status">{{ session('success') }}</div>
    @endif
    <div class="exp-alert exp-alert--ok" data-expense-feedback role="status" aria-live="polite" hidden></div>

    <section class="exp-card exp-summary-card" aria-labelledby="exp-summary-heading">
        <div class="exp-summary-head">
            <div>
                <h2 id="exp-summary-heading">Ringkasan Pengeluaran <x-ui.info-tip key="pengeluaran_operasional" label="Keterangan pengeluaran operasional"/></h2>
                <p class="exp-summary-sub">Biaya operasional di luar penjualan, piutang, dan laci kasir. Jurnal dikunci setelah disimpan agar riwayat keuangan tidak berubah diam-diam.</p>
            </div>
            <button type="button" class="exp-btn exp-btn--primary" onclick="openNewExpense()">+ Catat pengeluaran</button>
        </div>

        <div class="exp-facts">
            <div class="exp-fact exp-fact--primary">
                <span>Total uang keluar</span>
                <strong>Rp {{ $formatMoney($expenseSummary['total'] ?? '0.00') }}</strong>
                <small>Bulan berjalan</small>
            </div>
            <div class="exp-fact">
                <span>Transaksi</span>
                <strong>{{ $expenseSummary['count'] ?? 0 }}</strong>
                <small>Catatan bulan ini</small>
            </div>
            <div class="exp-fact">
                <span>Kas tunai</span>
                <strong>Rp {{ $formatMoney($expenseSummary['cash'] ?? '0.00') }}</strong>
                <small>Sumber 1101</small>
            </div>
            <div class="exp-fact">
                <span>Transfer bank</span>
                <strong>Rp {{ $formatMoney($expenseSummary['transfer'] ?? '0.00') }}</strong>
                <small>{{ $activeBankAccounts->count() > 1 ? $activeBankAccounts->count().' rekening bank aktif' : 'Sumber 1102' }}</small>
            </div>
        </div>
    </section>

    <section class="exp-card" aria-labelledby="exp-list-heading">
        <div class="exp-head">
            <div>
                <h2 id="exp-list-heading">Daftar pengeluaran</h2>
                <p class="exp-summary-sub">10 transaksi terakhir · {{ $expenseSummary['period_label'] ?? 'bulan ini' }}</p>
            </div>
            <form method="GET" action="{{ route('expenses.index') }}" class="exp-filter">
                <div class="field field-date-range">
                    <label for="expense-filter-start">Tanggal pengeluaran</label>
                    <input type="date" id="expense-filter-start" name="start_date" data-date-range-start="expense-filter-end" value="{{ request('start_date') }}">
                    <input type="date" id="expense-filter-end" name="end_date" data-date-range-end aria-label="Sampai tanggal pengeluaran" value="{{ request('end_date') }}">
                </div>
                <div class="field">
                    <label for="expense-filter-category">Akun beban</label>
                    <select id="expense-filter-category" name="category">
                        <option value="">Semua akun</option>
                        @foreach($expenseCategories ?? [] as $expenseCategory)
                            <option value="{{ $expenseCategory }}" @selected(request('category') === $expenseCategory)>{{ $expenseCategory }}{{ isset($accountNames[$expenseCategory]) ? ' · '.$accountNames[$expenseCategory] : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="expense-filter-method">Sumber dana</label>
                    <select id="expense-filter-method" name="payment_method">
                        <option value="">Semua sumber</option>
                        <option value="cash" @selected(request('payment_method') === 'cash')>Kas tunai</option>
                        <option value="transfer" @selected(request('payment_method') === 'transfer')>Transfer bank</option>
                    </select>
                </div>
                <div class="exp-filter-actions">
                    <button type="submit" class="exp-btn exp-btn--primary">Terapkan</button>
                    <a href="{{ route('expenses.index') }}" class="exp-btn">Reset</a>
                </div>
            </form>
        </div>

        <div class="exp-table-wrap">
            <table class="exp-table">
                <thead>
                    <tr>
                        <th scope="col">Nomor</th>
                        <th scope="col">Tanggal</th>
                        <th scope="col">Akun beban</th>
                        <th scope="col">Keterangan</th>
                        <th scope="col">Sumber</th>
                        <th scope="col" style="text-align:right">Jumlah</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($expenses as $ex)
                        @php
                            $accountLabel = isset($accountNames[$ex->category]) ? $ex->category.' · '.$accountNames[$ex->category] : $ex->category;
                            $expBankCode = (string) ($ex->bank_account_code ?? '1102');
                            $sourceLabel = $ex->payment_method === 'transfer'
                                ? (isset($bankNamesMap[$expBankCode]) ? $expBankCode.' · '.$bankNamesMap[$expBankCode] : 'Transfer bank')
                                : 'Kas tunai';
                        @endphp
                        <tr>
                            <td data-label="Nomor" class="exp-code">{{ $ex->expense_number }}</td>
                            <td data-label="Tanggal">{{ \Illuminate\Support\Carbon::parse($ex->expense_date)->locale('id')->translatedFormat('j M Y') }}</td>
                            <td data-label="Akun beban">{{ $accountLabel }}</td>
                            <td data-label="Keterangan">{{ $ex->description ?: '—' }}</td>
                            <td data-label="Sumber"><span class="exp-badge exp-badge--mute">{{ $sourceLabel }}</span></td>
                            <td data-label="Jumlah" class="exp-amount">Rp {{ $formatMoney($ex->amount) }}</td>
                            <td data-label="Status">
                                @if($ex->journal_entry_id)
                                    <span class="exp-badge exp-badge--ok" data-tip="Jurnal pengeluaran sudah seimbang dan terkunci.">Terkunci</span>
                                @else
                                    <span class="exp-badge exp-badge--warn" data-tip="Belum memiliki jurnal di buku besar.">Belum dijurnal</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="exp-empty"><strong>Belum ada catatan pengeluaran operasional.</strong>Tekan "+ Catat pengeluaran" di atas untuk mencatat biaya baru.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div id="modal-expense" class="modal-overlay {{ $errors->any() ? 'open' : '' }}" role="dialog" aria-modal="true" aria-labelledby="expense-modal-title">
        <div class="modal-dialog">
            <div class="modal-header">
                <div class="modal-title-wrapper">
                    <div>
                        <h2 id="expense-modal-title" class="modal-title">Catat Pengeluaran Operasional</h2>
                        <div class="exp-modal-sub">Beban operasional toko · mengurangi Kas/Bank</div>
                    </div>
                </div>
                <button type="button" class="modal-close-btn" onclick="closeModal('modal-expense')" aria-label="Tutup dialog">&times;</button>
            </div>

            <form action="{{ route('v2.expenses.store') }}" method="POST" id="expense-form" style="display:flex;flex-direction:column;flex:1;overflow:hidden;margin:0;">
                @csrf
                <div class="modal-body">
                    <p class="exp-alert exp-alert--bad" data-expense-error role="alert" tabindex="-1" hidden></p>
                    @if($errors->any())
                        <p class="exp-alert exp-alert--bad" role="alert">{{ $errors->first() }}</p>
                    @endif
                    <div class="form-group">
                        <label for="date">Tanggal</label>
                        <input type="date" name="date" id="date" class="form-control" value="{{ old('date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
                    </div>
                    <div class="form-group">
                        <label for="category">Akun biaya / kategori <x-ui.info-tip key="pengeluaran_operasional" label="Keterangan akun biaya operasional"/></label>
                        <select name="account_code" id="category" class="form-control" required>
                            <option value="">-- Pilih akun biaya operasional --</option>
                            @if(isset($expenseAccounts) && count($expenseAccounts) > 0)
                                @foreach($expenseAccounts as $acc)
                                    <option value="{{ $acc->account_code }}" @selected($selectedAccountCode === $acc->account_code)>{{ $acc->account_code }} - {{ $acc->account_name }}</option>
                                @endforeach
                            @else
                                <option value="6101" @selected($selectedAccountCode === '6101')>6101 - Operasional Toko</option>
                                <option value="6102" @selected($selectedAccountCode === '6102')>6102 - Listrik &amp; Air</option>
                                <option value="6103" @selected($selectedAccountCode === '6103')>6103 - Pemeliharaan &amp; Perbaikan</option>
                                <option value="6104" @selected($selectedAccountCode === '6104')>6104 - Perlengkapan &amp; ATK</option>
                                <option value="6105" @selected($selectedAccountCode === '6105')>6105 - Gaji &amp; Upah Tenaga</option>
                                <option value="6199" @selected($selectedAccountCode === '6199')>6199 - Lain-lain</option>
                            @endif
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="amount">Nominal pengeluaran</label>
                        <div class="exp-affix">
                            <span class="exp-pre" aria-hidden="true">Rp</span>
                            <input type="text" inputmode="decimal" name="amount" id="amount" class="form-control tabular-nums" placeholder="0" value="{{ old('amount') }}" required data-currency-input data-currency-scale="2">
                        </div>
                        <div class="exp-balance-hint">Pemisah ribuan muncul otomatis saat mengetik. Nominal tidak boleh melebihi saldo sumber dana.</div>
                    </div>

                    <div class="form-group">
                        <label id="expense-source-label" for="payment_method">Sumber dana</label>
                        <select name="source" id="payment_method" class="exp-source-native" data-native-select tabindex="-1" aria-hidden="true" required>
                            <option value="cash" @selected($selectedSource === 'cash')>Kas tunai (1101)</option>
                            <option value="bank" @selected($selectedSource === 'bank')>Transfer bank</option>
                        </select>
                        <div class="exp-seg" role="radiogroup" aria-labelledby="expense-source-label" data-expense-source-seg>
                            <button type="button" role="radio" data-source-value="cash" aria-checked="{{ $selectedSource === 'cash' ? 'true' : 'false' }}" tabindex="{{ $selectedSource === 'cash' ? '0' : '-1' }}">Kas tunai (1101)</button>
                            <button type="button" role="radio" data-source-value="bank" aria-checked="{{ $selectedSource === 'bank' ? 'true' : 'false' }}" tabindex="{{ $selectedSource === 'bank' ? '0' : '-1' }}">Transfer bank</button>
                        </div>
                        <div class="exp-balance-hint">
                            Saldo Kas Tunai: <strong>Rp {{ $formatMoney($cashBalance) }}</strong>
                            · Total Saldo Bank: <strong>Rp {{ $formatMoney($bankBalance) }}</strong>
                        </div>
                    </div>

                    <div class="form-group" data-expense-bank-field @if($selectedSource !== 'bank') hidden @endif>
                        <label for="expense_bank_account">Rekening bank sumber dana</label>
                        <select name="bank_account_code" id="expense_bank_account" class="form-control" @disabled($selectedSource !== 'bank')>
                            @foreach($activeBankAccounts as $bankAcc)
                                @php $bCode = (string) $bankAcc->account_code; @endphp
                                <option value="{{ $bCode }}" @selected($selectedBankCode === $bCode)>{{ $bCode }} - {{ $bankAcc->account_name }} (Saldo Rp {{ $formatMoney($bankBalancesMap[$bCode] ?? '0.00') }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom:0;">
                        <label for="description">Keterangan <span class="exp-opt">Opsional</span></label>
                        <input type="text" name="description" id="description" class="form-control" maxlength="255" placeholder="Contoh: Pembelian token listrik toko" value="{{ old('description') }}">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modal-expense')">Batal</button>
                    <button type="submit" id="btn-submit-expense" class="btn">Simpan pengeluaran</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function expenseModalReset() {
    var date = document.getElementById('date');
    var cat = document.getElementById('category');
    var amt = document.getElementById('amount');
    var src = document.getElementById('payment_method');
    var desc = document.getElementById('description');
    var err = document.querySelector('#modal-expense [data-expense-error]');
    if (date) date.value = '{{ now()->toDateString() }}';
    if (cat) { cat.value = ''; cat.dispatchEvent(new Event('change', { bubbles: true })); }
    if (amt && typeof window.setCurrencyInput === 'function') window.setCurrencyInput(amt, '');
    else if (amt) amt.value = '';
    if (src) { src.value = 'cash'; src.dispatchEvent(new Event('change', { bubbles: true })); }
    if (desc) desc.value = '';
    if (err) { err.hidden = true; err.textContent = ''; }
}

function openNewExpense() {
    expenseModalReset();
    openModal('modal-expense');
}
</script>
@include('v2.expense-script')
@endsection