<?php

namespace App\Services\V2;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Rekap tutup shift, read-only. Menampilkan rincian penjualan (tunai/transfer/kredit),
 * aliran uang laci (kas awal + masuk - keluar), dan jumlah pengajuan tunai yang masih
 * menunggu di laci ini, agar admin membandingkannya dengan uang fisik yang dihitung.
 * Tidak menulis apa pun; kunci baris shift hanya untuk bacaan yang konsisten.
 */
final class ShiftClosingSummary
{
    public function __construct(private readonly Operations $operations) {}

    public function build(int $actor, int $shiftId): array
    {
        $role = $this->operations->role($actor);
        abort_unless(in_array($role, ['admin', 'cashier'], true), 403);

        return DB::transaction(function () use ($actor, $role, $shiftId): array {
            $shift = DB::table('v2_shifts')->where('id', $shiftId)->lockForUpdate()->first();
            abort_unless($shift, 404);
            if ($role !== 'admin' && ! $shift->is_shared && (int) $shift->user_id !== $actor) {
                abort(403);
            }
            if ($shift->status !== 'open') {
                throw ValidationException::withMessages(['shift' => 'Rekap hanya tersedia untuk shift yang masih terbuka.']);
            }

            $cashier = (string) DB::table('users')->where('id', $shift->user_id)->value('name');

            $sales = DB::table('v2_sales')->where('shift_id', $shiftId)
                ->when($role !== 'admin', fn ($query) => $query->where('user_id', $actor))
                ->get(['id', 'payment_method', 'total']);
            $salesCount = 0;
            $salesTotal = $this->zero();
            $salesCash = $this->zero();
            $salesTransfer = $this->zero();
            $salesCredit = $this->zero();
            foreach ($sales as $sale) {
                $salesCount++;
                $total = Money::value((string) $sale->total, 2);
                $salesTotal = $salesTotal->plus($total);
                match ($sale->payment_method) {
                    'cash' => $salesCash = $salesCash->plus($total),
                    'transfer' => $salesTransfer = $salesTransfer->plus($total),
                    'credit' => $salesCredit = $salesCredit->plus($total),
                    default => null,
                };
            }

            $movements = DB::table('v2_shift_movements')->where('shift_id', $shiftId)->orderBy('id')->get(['id', 'direction', 'kind', 'amount']);
            $cashIn = $this->zero();
            $cashOut = $this->zero();
            $moveSales = $this->zero();
            $moveCollections = $this->zero();
            $moveOtherIn = $this->zero();
            $moveSupplierOut = $this->zero();
            $moveOtherOut = $this->zero();
            foreach ($movements as $movement) {
                $amount = Money::value((string) $movement->amount, 2);
                if ($movement->direction === 'in') {
                    $cashIn = $cashIn->plus($amount);
                    if ($movement->kind === 'sale') {
                        $moveSales = $moveSales->plus($amount);
                    } elseif ($movement->kind === 'collection') {
                        $moveCollections = $moveCollections->plus($amount);
                    } else {
                        $moveOtherIn = $moveOtherIn->plus($amount);
                    }
                } else {
                    $cashOut = $cashOut->plus($amount);
                    if ($movement->kind === 'supplier') {
                        $moveSupplierOut = $moveSupplierOut->plus($amount);
                    } else {
                        $moveOtherOut = $moveOtherOut->plus($amount);
                    }
                }
            }

            $opening = Money::value((string) $shift->opening_cash, 2);
            $expected = $opening->plus($cashIn)->minus($cashOut);

            $pending = DB::table('v2_payments')->where('shift_id', $shiftId)->where('method', 'cash')->where('status', 'proposed')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
            $fingerprint = $this->operations->closingFingerprint($shiftId);

            return [
                'shift_id' => $shiftId,
                'cashier' => $cashier,
                'opened_by' => $cashier,
                'is_shared' => (bool) $shift->is_shared,
                'opened_at' => (string) $shift->opened_at,
                'scope' => $shift->is_shared ? 'store' : 'shift',
                'sales_scope' => $role === 'admin' ? 'all' : 'own',
                'sales' => [
                    'count' => $salesCount,
                    'total' => $this->str($salesTotal),
                    'cash' => $this->str($salesCash),
                    'transfer' => $this->str($salesTransfer),
                    'credit' => $this->str($salesCredit),
                ],
                'cash' => [
                    'opening' => $this->str($opening),
                    'sales' => $this->str($moveSales),
                    'collections' => $this->str($moveCollections),
                    'other_in' => $this->str($moveOtherIn),
                    'in' => $this->str($cashIn),
                    'supplier_out' => $this->str($moveSupplierOut),
                    'other_out' => $this->str($moveOtherOut),
                    'out' => $this->str($cashOut),
                    'expected' => $this->str($expected),
                ],
                'pending_drawer_payments' => count($pending),
                'fingerprint' => $fingerprint,
            ];
        });
    }

    private function zero(): BigDecimal
    {
        return BigDecimal::zero()->toScale(2);
    }

    private function str(BigDecimal $value): string
    {
        return (string) $value->toScale(2);
    }
}
