<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Aktivitas supplier — {{ $supplier->code }}</title>
@include('v2.ui-tokens')
<style>
body{font-family:system-ui,sans-serif;color:var(--ui-ink);margin:var(--ui-space-panel);line-height:var(--ui-leading-body)}
h1{font-size:var(--ui-h2)}table{width:100%;border-collapse:collapse}th,td{text-align:left;vertical-align:top;padding:var(--ui-space-control);border-bottom:var(--ui-stroke) solid var(--ui-line)}.ui-num{text-align:right;font-variant-numeric:tabular-nums}.ui-sub{display:block;font-size:var(--ui-t-caption)}.sr{position:absolute;clip-path:inset(50%);width:var(--ui-stroke);height:var(--ui-stroke);overflow:hidden}button{padding:var(--ui-space-control) var(--ui-space-section);min-height:var(--ui-tap)}
@media print{button{display:none}thead{display:table-header-group}tr{break-inside:avoid}body{margin:0}}
</style></head><body>
<button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
<h1>Aktivitas supplier</h1><p><strong>{{ $supplier->name }}</strong> · {{ $supplier->code }}</p>
@if($supplier->notes)<p>{{ $supplier->notes }}</p>@endif
<p>Tanggal pencatatan {{ $from }} – {{ $to }} · {{ $entries->count() }} aktivitas · Dicetak {{ now()->format('d/m/Y H:i') }}</p>
<p>Utang berjalan saat dicetak: <strong>{{ \App\Services\V2\Money::display($outstanding) }}</strong></p>
@include('v2.supplier-activity-table', ['printing'=>true])
<p>Dokumen rincian aktivitas, bukan bukti pembayaran tunai/transfer. Hanya pembayaran berstatus Terverifikasi yang telah dibukukan. Pengajuan menunggu/ditolak tidak mengurangi utang. Saldo di atas adalah kondisi saat dicetak, bukan saldo penutupan pada akhir rentang.</p>
</body></html>
