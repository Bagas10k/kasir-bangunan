<?php

namespace App\Http\Controllers;

use App\Services\PosCheckoutService;
use App\Services\RitaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PosController extends Controller
{
    public function index(Request $request)
    {
        $productQ = trim((string) $request->input('product_q', ''));
        $productCategory = trim((string) $request->input('product_category', ''));
        $productStock = (string) $request->input('product_stock', 'all');

        $productsQuery = DB::table('products')
            ->where('is_active', true)
            ->when($productCategory !== '', fn ($q) => $q->where('category', $productCategory))
            ->when($productQ !== '', function ($q) use ($productQ) {
                $lower = mb_strtolower($productQ);
                $q->where(function ($sub) use ($lower) {
                    $sub->where(DB::raw('LOWER(sku)'), 'like', "%{$lower}%")
                        ->orWhere(DB::raw('LOWER(name)'), 'like', "%{$lower}%");
                });
            })
            ->when($productStock === 'available', function ($q) {
                $q->where('stock_quantity', '>', 0);
            })
            ->when($productStock === 'low', function ($q) {
                $q->where('stock_quantity', '>', 0)
                  ->where('stock_quantity', '<=', 10);
            })
            ->when($productStock === 'out', function ($q) {
                $q->where('stock_quantity', '<=', 0);
            })
            ->orderBy('name');

        $products = $productsQuery
            ->paginate(20, ['*'], 'product_page')
            ->appends($request->query());

        // Dashboard summaries (top products, low stock, stock summary)

        $topProducts = DB::table('sale_items')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('products.is_active', true)
            ->select('products.id', 'products.name', 'products.sku', 'products.unit', DB::raw('SUM(sale_items.quantity) as total_qty'))
            ->groupBy('products.id', 'products.name', 'products.sku', 'products.unit')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        $lowStock = DB::table('products')
            ->where('is_active', true)
            ->where('stock_quantity', '>', 0)
            ->where('stock_quantity', '<=', 10)
            ->orderBy('stock_quantity')
            ->limit(5)
            ->get();

        $stockSummary = [
            'products' => DB::table('products')->where('is_active', true)->count(),
            'low' => DB::table('products')->where('is_active', true)->where('stock_quantity', '>', 0)->where('stock_quantity', '<=', 10)->count(),
            'out' => DB::table('products')->where('is_active', true)->where('stock_quantity', '<=', 0)->count(),
        ];

        $fleetReady = \Illuminate\Support\Facades\Schema::hasColumn('ritases', 'inventory_receipt_id');
        $vehicles = $fleetReady
            ? DB::table('vehicles')->where('status', 'active')->orderBy('license_plate')->get(['id', 'license_plate', 'vehicle_type'])
            : collect();
        $categories = DB::table('products')->where('is_active', true)->whereNotNull('category')->where('category', '<>', '')->distinct()->orderBy('category')->pluck('category');

        return view('pos.index', compact('products', 'productQ', 'productCategory', 'productStock', 'categories', 'topProducts', 'lowStock', 'stockSummary', 'vehicles', 'fleetReady'));
    }

    public function salesHistory(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'in:10,25,50'],
            'sort' => ['nullable', 'in:invoice_number,payment_method,total_amount'],
            'dir' => ['nullable', 'in:asc,desc'],
            'payment_method' => ['nullable', 'in:cash,transfer,credit'],
            'customer_type' => ['nullable', 'in:retail,contractor'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);
        $search = trim((string) $request->input('search', ''));
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50], true) ? (int) $request->input('per_page') : 10;
        $sort = (string) $request->input('sort', 'id');
        $dir = $request->input('dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $sortable = ['invoice_number' => 'invoice_number', 'payment_method' => 'payment_method', 'total_amount' => 'total_amount'];
        $sortCol = $sortable[$sort] ?? 'id';

        $sales = DB::table('sales')
            ->when($search !== '', function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%");
            })
            ->when(!empty($filters['payment_method']), fn ($q) => $q->where('payment_method', $filters['payment_method']))
            ->when(!empty($filters['customer_type']), fn ($q) => $q->where('customer_type', $filters['customer_type']))
            ->when(!empty($filters['start_date']), fn ($q) => $q->whereDate('created_at', '>=', $filters['start_date']))
            ->when(!empty($filters['end_date']), fn ($q) => $q->whereDate('created_at', '<=', $filters['end_date']))
            ->orderBy($sortCol, $dir)
            ->paginate($perPage);

        return response()->json([
            'data' => $sales->items(),
            'pagination' => [
                'current_page' => $sales->currentPage(),
                'last_page' => $sales->lastPage(),
                'from' => $sales->firstItem(),
                'to' => $sales->lastItem(),
                'total' => $sales->total(),
                'next_page_url' => $sales->nextPageUrl(),
                'prev_page_url' => $sales->previousPageUrl(),
            ],
        ]);
    }

    public function searchProducts(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:50'],
        ]);
        $q = trim((string) ($filters['q'] ?? ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['data' => []]);
        }

        $lower = mb_strtolower($q);
        $products = DB::table('products')
            ->select('id', 'sku', 'name', 'unit', 'category', 'stock_quantity', 'selling_price')
            ->where('is_active', true)
            ->when(!empty($filters['category']), fn ($query) => $query->where('category', $filters['category']))
            ->where(function ($query) use ($lower) {
                $query->where(DB::raw('LOWER(sku)'), 'like', "%{$lower}%")
                      ->orWhere(DB::raw('LOWER(name)'), 'like', "%{$lower}%");
            })
            ->orderBy('name')
            ->limit(10)
            ->get();

        return response()->json(['data' => $products]);
    }

    public function checkout(Request $request, PosCheckoutService $service, RitaseService $ritaseService)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => ['required', 'regex:/^\d+(\.\d{1,4})?$/', 'numeric', 'min:0.0001'],
            'payment_method' => 'required|in:cash,transfer,credit',
            'paid_amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/', 'numeric', 'min:0'],
            'customer_type' => 'nullable|string',
            'use_armada' => 'nullable|in:0,1',
            'vehicle_id' => 'required_if:use_armada,1|nullable|integer|exists:vehicles,id',
            'ritase_amount' => ['required_if:use_armada,1', 'nullable', 'regex:/^\d{1,17}(\.\d{1,2})?$/', 'numeric', 'gt:0'],
        ]);

        if ((string) $request->input('use_armada') !== '1' && ($request->filled('vehicle_id') || $request->filled('ritase_amount'))) {
            return response()->json(['error' => 'Pilih penggunaan armada untuk mengisi biaya ritase.'], 422);
        }

        if ((string) $request->input('use_armada') === '1' && !\Illuminate\Support\Facades\Schema::hasColumn('ritases', 'inventory_receipt_id')) {
            return response()->json(['error' => 'Pilihan armada belum tersedia sampai pembaruan database selesai.'], 422);
        }

        // Sale and trip share one transaction; failure of either rolls back both.
        try {
            $result = DB::transaction(function () use ($request, $service, $ritaseService) {
                $sale = $service->checkout(
                    $request->items,
                    $request->payment_method,
                    (string) $request->paid_amount,
                    $request->customer_type ?? 'retail'
                );
                if ((string) $request->input('use_armada') === '1') {
                    $sale['ritase'] = $ritaseService->recordPendingTrip(
                        (int) $request->vehicle_id,
                        (string) $request->ritase_amount,
                        (int) $sale['sale_id']
                    );
                }
                return $sale;
            });
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
            return redirect()->back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        return redirect()->route('pos.index', ['receipt' => $result['sale_id']]);
    }

    public function receipt($id)
    {
        $sale = DB::table('sales')->where('id', $id)->first();
        if (!$sale) {
            abort(404, 'Sale not found');
        }

        $items = DB::table('sale_items')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('sale_items.sale_id', $id)
            ->select('products.name', 'products.unit', 'sale_items.quantity', 'sale_items.unit_price', 'sale_items.subtotal')
            ->get();

        if (request()->wantsJson()) {
            return response()->json(['sale' => $sale, 'items' => $items]);
        }

        return view('pos.receipt', compact('sale', 'items'));
    }

    public function dashboardSales(Request $request)
    {
        $period = (string) $request->input('period', '7d');
        if (!in_array($period, ['today', '7d', '30d'], true)) {
            $period = '7d';
        }

        if ($period === 'today') {
            $startDate = now()->startOfDay();
            $endDate = now();
            $label = 'Hari Ini';

            $sales = DB::table('sales')
                ->where('created_at', '>=', $startDate)
                ->where('created_at', '<=', $endDate)
                ->get(['created_at', 'total_amount']);

            $revenue = (float) $sales->sum('total_amount');
            $txCount = (int) $sales->count();

            $hourlyTotals = [];
            foreach ($sales as $sale) {
                $hour = (int) \Carbon\Carbon::parse($sale->created_at)->format('H');
                $hourlyTotals[$hour] = ($hourlyTotals[$hour] ?? 0) + (float) $sale->total_amount;
            }

            $buckets = [];
            for ($h = 0; $h < 24; $h++) {
                $buckets[] = [
                    'label' => sprintf('%02d:00', $h),
                    'total' => (float) ($hourlyTotals[$h] ?? 0),
                ];
            }
        } else {
            $numDays = ($period === '30d') ? 30 : 7;
            $startDate = now()->subDays($numDays - 1)->startOfDay();
            $endDate = now();
            $label = ($period === '30d') ? '30 Hari Terakhir' : '7 Hari Terakhir';

            $sales = DB::table('sales')
                ->where('created_at', '>=', $startDate)
                ->where('created_at', '<=', $endDate)
                ->get(['created_at', 'total_amount']);

            $revenue = (float) $sales->sum('total_amount');
            $txCount = (int) $sales->count();

            $dailyTotals = [];
            foreach ($sales as $sale) {
                $dayKey = \Carbon\Carbon::parse($sale->created_at)->toDateString();
                $dailyTotals[$dayKey] = ($dailyTotals[$dayKey] ?? 0) + (float) $sale->total_amount;
            }

            $buckets = [];
            for ($i = $numDays - 1; $i >= 0; $i--) {
                $dt = now()->subDays($i);
                $key = $dt->toDateString();
                $buckets[] = [
                    'label' => $dt->format('d/m'),
                    'total' => (float) ($dailyTotals[$key] ?? 0),
                ];
            }
        }

        return response()->json([
            'period' => $period,
            'label' => $label,
            'revenue' => $revenue,
            'transaction_count' => $txCount,
            'buckets' => $buckets,
        ]);
    }
}
