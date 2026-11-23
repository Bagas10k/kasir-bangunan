<?php

namespace App\Services\V2;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

final class SupplierActivity
{
    /** @return array<string, mixed> Read-only activity; invoice balances are current, not historical closing balances. */
    public function build(int $supplierId, string $from, string $to, ?string $entryType = null, ?int $entryId = null): array
    {
        $outstanding = BigDecimal::zero();
        foreach (DB::table('v2_invoices')->where('party_id', $supplierId)->where('direction', 'payable')
            ->whereNull('voided_at')->select(['amount', 'paid'])->cursor() as $invoice) {
            $remaining = BigDecimal::of((string) $invoice->amount)->minus((string) $invoice->paid);
            if ($remaining->isPositive()) {
                $outstanding = $outstanding->plus($remaining);
            }
        }
        $invoices = DB::table('v2_invoices')->where('party_id', $supplierId)->where('direction', 'payable')
            ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])->get();
        $payments = DB::table('v2_payments as pm')->join('v2_invoices as i', 'i.id', '=', 'pm.invoice_id')
            ->where('i.party_id', $supplierId)->where('i.direction', 'payable')
            ->whereBetween('pm.created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->get(['pm.*', 'i.number as invoice_number', 'i.voided_at as invoice_voided_at']);
        $entries = collect();
        $billed = BigDecimal::zero();
        $settled = BigDecimal::zero();
        $awaiting = BigDecimal::zero();
        foreach ($invoices as $invoice) {
            if ($invoice->voided_at === null) {
                $billed = $billed->plus((string) $invoice->amount);
            }
            $entries->push(['type' => 'invoice', 'id' => (int) $invoice->id, 'date' => (string) $invoice->created_at,
                'label' => 'Tagihan supplier', 'number' => $invoice->number, 'amount' => (string) $invoice->amount,
                'status' => $invoice->voided_at ? 'void' : $invoice->status, 'method' => null, 'reference' => null]);
        }
        foreach ($payments as $payment) {
            if ($payment->invoice_voided_at === null) {
                if ($payment->status === 'posted') {
                    $settled = $settled->plus((string) $payment->amount);
                } elseif (in_array($payment->status, ['pending', 'proposed'], true)) {
                    $awaiting = $awaiting->plus((string) $payment->amount);
                }
            }
            $entries->push(['type' => 'payment', 'id' => (int) $payment->id, 'date' => (string) $payment->created_at,
                'label' => 'Pembayaran', 'number' => $payment->invoice_number, 'amount' => (string) $payment->amount,
                'status' => $payment->status, 'method' => $payment->method, 'reference' => $payment->reference]);
        }
        $entries = $entries->sortByDesc(fn (array $entry): string => $entry['date'].'|'.str_pad((string) $entry['id'], 20, '0', STR_PAD_LEFT))->values();
        if ($entryType !== null) {
            $entries = $entries->filter(fn (array $entry): bool => $entry['type'] === $entryType && $entry['id'] === $entryId)->values();
            abort_if($entries->isEmpty(), 404);
        }

        return ['entries' => $entries, 'outstanding' => (string) $outstanding->toScale(2),
            'billed' => (string) $billed->toScale(2), 'settled' => (string) $settled->toScale(2), 'awaiting' => (string) $awaiting->toScale(2)];
    }
}
