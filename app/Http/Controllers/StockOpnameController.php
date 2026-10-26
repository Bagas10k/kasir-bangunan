<?php

namespace App\Http\Controllers;

use App\Services\StockOpnameService;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockOpnameController extends Controller
{
    public function index(Request $request)
    {
        $products = DB::table('products')->where('is_active', true)->orderBy('name')->get();

        $search = trim((string) $request->input('search', ''));
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50], true) ? (int) $request->input('per_page') : 15;
        $sort = (string) $request->input('sort', 'opname_date');
        $dir = $request->input('dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $sortable = [
            'opname_number' => 'opname_number',
            'opname_date' => 'opname_date',
            'status' => 'status',
            'notes' => 'notes',
        ];
        $sortCol = $sortable[$sort] ?? 'id';

        $recentOpnames = DB::table('stock_opnames')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('opname_number', 'like', "%{$search}%")
                      ->orWhere('notes', 'like', "%{$search}%")
                      ->orWhere('status', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortCol, $dir)
            ->paginate($perPage)
            ->appends($request->query());

                $startOfMonth = now()->startOfMonth()->toDateString();
        $endOfMonth = now()->endOfMonth()->toDateString();
        $periodLabel = now()->translatedFormat('F Y');

        $opnameSummary = [
            'posted_count' => 0,
            'items_counted' => 0,
            'stock_increase_value' => BigDecimal::zero(),
            'stock_decrease_value' => BigDecimal::zero(),
            'period_label' => 'Bulan Ini (' . $periodLabel . ')',
        ];

        $postedOpnames = DB::table('stock_opnames')
            ->where('status', 'posted')
            ->whereBetween('opname_date', [$startOfMonth, $endOfMonth]);
        $opnameSummary['posted_count'] = (clone $postedOpnames)->count();

        DB::table('stock_opname_items')
            ->joinSub($postedOpnames->select('id'), 'monthly_posted_opnames', function ($join) {
                $join->on('stock_opname_items.stock_opname_id', '=', 'monthly_posted_opnames.id');
            })
            ->orderBy('stock_opname_items.id')
            ->chunk(500, function ($items) use (&$opnameSummary) {
                foreach ($items as $item) {
                    $opnameSummary['items_counted']++;
                    $value = BigDecimal::of((string) ($item->value_difference ?? '0'));
                    if ($value->isPositive()) {
                        $opnameSummary['stock_increase_value'] = $opnameSummary['stock_increase_value']->plus($value);
                    } elseif ($value->isNegative()) {
                        $opnameSummary['stock_decrease_value'] = $opnameSummary['stock_decrease_value']->plus($value->abs());
                    }
                }
            });

        return view('stock_opname.index', compact('products', 'recentOpnames', 'search', 'perPage', 'sort', 'dir', 'opnameSummary'));
    }

    public function store(Request $request, StockOpnameService $service)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.physical_qty' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:255',
        ]);

        try {
            $result = $service->recordOpname($request->items, $request->notes);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
            return redirect()->back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        return redirect()->route('opname.index')->with('success', 'Stock opname recorded');
    }
}
