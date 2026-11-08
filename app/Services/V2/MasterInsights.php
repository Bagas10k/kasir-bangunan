<?php

namespace App\Services\V2;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/**
 * Rincian relasi untuk layar master: apa yang dipasok supplier, dan ke mana mobil dipakai.
 *
 * Hanya SELECT. Uang dijumlahkan dengan BigDecimal, bukan SUM SQL, agar presisi desimal terjaga.
 */
final class MasterInsights
{
    private const HISTORY_LIMIT = 8;

    /** @return array<int, array<string, mixed>> rincian per supplier */
    public function suppliers(array $supplierIds): array
    {
        if ($supplierIds === []) {
            return [];
        }

        $products = DB::table('v2_product_suppliers as link')
            ->join('products', 'products.id', '=', 'link.product_id')
            ->whereIn('link.supplier_id', $supplierIds)
            ->orderBy('products.name')
            ->get(['link.supplier_id', 'link.is_primary', 'products.sku', 'products.name', 'products.stock_quantity', 'products.is_active'])
            ->groupBy('supplier_id');

        $invoices = DB::table('v2_invoices')
            ->where('direction', 'payable')
            ->whereIn('party_id', $supplierIds)
            ->orderByDesc('id')
            ->get(['id', 'party_id', 'number', 'amount', 'paid', 'status', 'created_at', 'receipt_id'])
            ->groupBy('party_id');

        $payments = DB::table('v2_payments as pm')
            ->join('v2_invoices as i', 'i.id', '=', 'pm.invoice_id')
            ->where('i.direction', 'payable')
            ->whereIn('i.party_id', $supplierIds)
            ->orderByDesc('pm.id')
            ->get(['pm.id', 'pm.amount', 'pm.method', 'pm.status', 'pm.reference', 'pm.created_at', 'i.party_id', 'i.number as invoice_number'])
            ->groupBy('party_id');

        $out = [];
        foreach ($supplierIds as $id) {
            $id = (int) $id;
            $supplierInvoices = $invoices->get($id, collect());
            $supplierPayments = $payments->get($id, collect());

            $outstanding = BigDecimal::zero();
            $billed = BigDecimal::zero();
            foreach ($supplierInvoices as $invoice) {
                $amount = BigDecimal::of((string) $invoice->amount);
                $paid = BigDecimal::of((string) $invoice->paid);
                $billed = $billed->plus($amount);
                $remaining = $amount->minus($paid);
                if ($remaining->isGreaterThan(0)) {
                    $outstanding = $outstanding->plus($remaining);
                }
            }
            $settled = BigDecimal::zero();
            $awaiting = BigDecimal::zero();
            foreach ($supplierPayments as $payment) {
                $amount = BigDecimal::of((string) $payment->amount);
                if ($payment->status === 'posted') {
                    $settled = $settled->plus($amount);
                } elseif (in_array($payment->status, ['pending', 'proposed'], true)) {
                    $awaiting = $awaiting->plus($amount);
                }
            }

            $history = $supplierInvoices->map(fn ($invoice) => [
                'type' => 'invoice',
                'label' => 'Tagihan '.($invoice->number ?: '#'.$invoice->id),
                'amount' => (string) $invoice->amount,
                'status' => $invoice->status ?: 'unpaid',
                'date' => (string) $invoice->created_at,
            ])->concat($supplierPayments->map(fn ($payment) => [
                'type' => 'payment',
                'label' => 'Pembayaran '.($payment->invoice_number ?: '').($payment->reference ? ' · '.$payment->reference : ''),
                'amount' => (string) $payment->amount,
                'status' => $payment->status,
                'date' => (string) $payment->created_at,
            ]))->sortByDesc('date')->take(self::HISTORY_LIMIT)->values()->all();

            $out[$id] = [
                'products' => $products->get($id, collect())->map(fn ($product) => [
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'primary' => (bool) $product->is_primary,
                    'active' => (bool) $product->is_active,
                ])->all(),
                'billed' => (string) $billed->toScale(2),
                'outstanding' => (string) $outstanding->toScale(2),
                'settled' => (string) $settled->toScale(2),
                'awaiting' => (string) $awaiting->toScale(2),
                'invoice_count' => $supplierInvoices->count(),
                'history' => $history,
            ];
        }

        return $out;
    }

    /** @return array<int, array<string, mixed>> riwayat pemakaian per mobil */
    public function vehicles(array $vehicleIds): array
    {
        if ($vehicleIds === []) {
            return [];
        }

        $deliveries = DB::table('v2_deliveries as d')
            ->leftJoin('v2_drivers as dr', 'dr.id', '=', 'd.driver_id')
            ->whereIn('d.vehicle_id', $vehicleIds)
            ->orderByDesc('d.id')
            ->get(['d.id', 'd.vehicle_id', 'd.number', 'd.destination', 'd.status', 'd.created_at', 'd.driver_name', 'dr.name as driver'])
            ->groupBy('vehicle_id');

        $trips = DB::table('v2_trips as t')
            ->leftJoin('v2_drivers as dr', 'dr.id', '=', 't.driver_id')
            ->whereIn('t.vehicle_id', $vehicleIds)
            ->orderByDesc('t.id')
            ->get(['t.id', 't.vehicle_id', 't.date', 't.destination', 't.total_cost', 't.status', 't.driver_name', 'dr.name as driver'])
            ->groupBy('vehicle_id');

        $out = [];
        foreach ($vehicleIds as $id) {
            $id = (int) $id;
            $vehicleDeliveries = $deliveries->get($id, collect());
            $vehicleTrips = $trips->get($id, collect());

            $cost = BigDecimal::zero();
            foreach ($vehicleTrips as $trip) {
                $cost = $cost->plus(BigDecimal::of((string) ($trip->total_cost ?? '0')));
            }

            $history = $vehicleDeliveries->map(fn ($delivery) => [
                'type' => 'delivery',
                'label' => 'Surat jalan '.$delivery->number,
                'destination' => $delivery->destination ?: '—',
                'driver' => $delivery->driver ?: ($delivery->driver_name ?: '—'),
                'status' => $delivery->status,
                'amount' => null,
                'date' => (string) $delivery->created_at,
            ])->concat($vehicleTrips->map(fn ($trip) => [
                'type' => 'trip',
                'label' => 'Biaya ritase',
                'destination' => $trip->destination ?: '—',
                'driver' => $trip->driver ?: ($trip->driver_name ?: '—'),
                'status' => $trip->status,
                'amount' => (string) ($trip->total_cost ?? '0'),
                'date' => (string) $trip->date,
            ]))->sortByDesc('date')->take(self::HISTORY_LIMIT)->values()->all();

            $out[$id] = [
                'deliveries' => $vehicleDeliveries->count(),
                'trips' => $vehicleTrips->count(),
                'trip_cost' => (string) $cost->toScale(2),
                'last_used' => $history[0]['date'] ?? null,
                'history' => $history,
            ];
        }

        return $out;
    }
}
