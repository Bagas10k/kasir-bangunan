<?php

namespace App\Http\Controllers;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReceivablesController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:50'],
            'customer_type' => ['nullable', 'in:retail,contractor'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'per_page' => ['nullable', 'integer', 'in:10,15,25,50,100'],
        ]);

        $query = DB::table('sales')
            ->where('payment_method', 'credit')
            ->whereColumn('total_amount', '>', 'paid_amount');

        if (!empty($validated['search'])) {
            $search = trim($validated['search']);
            $query->where('invoice_number', 'like', "%{$search}%");
        }

        if (!empty($validated['customer_type'])) {
            $query->where('customer_type', $validated['customer_type']);
        }

        if (!empty($validated['start_date'])) {
            $query->whereDate('created_at', '>=', $validated['start_date']);
        }

        if (!empty($validated['end_date'])) {
            $query->whereDate('created_at', '<=', $validated['end_date']);
        }

        $summary = [
            'count' => 0,
            'total' => BigDecimal::zero(),
            'paid' => BigDecimal::zero(),
            'remaining' => BigDecimal::zero(),
        ];
        // Aggregate every filtered receivable in bounded batches, not just the current page.
        (clone $query)->select(['id', 'total_amount', 'paid_amount'])->orderBy('id')->chunk(500, function ($rows) use (&$summary) {
            foreach ($rows as $row) {
                $total = BigDecimal::of((string) $row->total_amount);
                $paid = BigDecimal::of((string) $row->paid_amount);
                $summary['count']++;
                $summary['total'] = $summary['total']->plus($total);
                $summary['paid'] = $summary['paid']->plus($paid);
                $summary['remaining'] = $summary['remaining']->plus($total->minus($paid));
            }
        });
        foreach (['total', 'paid', 'remaining'] as $amount) {
            $summary[$amount] = (string) $summary[$amount]->toScale(2, RoundingMode::HalfUp);
        }

        $perPage = (int) ($validated['per_page'] ?? 15);
        $receivables = $query->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        $customerTypes = DB::table('sales')
            ->where('payment_method', 'credit')
            ->whereNotNull('customer_type')
            ->distinct()
            ->pluck('customer_type');

        // Hitung saldo sisa per row menggunakan Brick\Math\BigDecimal dari string
        $receivables->getCollection()->transform(function ($item) {
            $total = BigDecimal::of((string) $item->total_amount);
            $paid = BigDecimal::of((string) $item->paid_amount);
            $item->remaining_balance = (string) $total->minus($paid);
            return $item;
        });

        return view('receivables.index', [
            'receivables' => $receivables,
            'customerTypes' => $customerTypes,
            'summary' => $summary,
            'filters' => [
                'search' => $validated['search'] ?? '',
                'customer_type' => $validated['customer_type'] ?? '',
                'start_date' => $validated['start_date'] ?? '',
                'end_date' => $validated['end_date'] ?? '',
                'per_page' => $perPage,
            ],
        ]);
    }
}
