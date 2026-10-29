<?php

namespace App\Http\Controllers;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:50'],
            'unit' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'stock' => ['nullable', Rule::in(['low', 'out'])],
            'per_page' => ['nullable', Rule::in(['10', '25', '50'])],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = (int) ($filters['per_page'] ?? 10);

        $products = DB::table('products')
            ->when($search !== '', function ($query) use ($search) {
                $needle = mb_strtolower($search);
                $query->where(function ($where) use ($needle) {
                    $where->where(DB::raw('LOWER(sku)'), 'like', "%{$needle}%")
                        ->orWhere(DB::raw('LOWER(name)'), 'like', "%{$needle}%");
                });
            })
            ->when(! empty($filters['category']), fn ($query) => $query->where('category', $filters['category']))
            ->when(! empty($filters['unit']), fn ($query) => $query->where('unit', $filters['unit']))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when(($filters['stock'] ?? null) === 'low', fn ($query) => $query->where('stock_quantity', '>', 0)->whereColumn('stock_quantity', '<=', 'min_stock'))
            ->when(($filters['stock'] ?? null) === 'out', fn ($query) => $query->where('stock_quantity', '<=', 0))
            ->orderBy('name')
            ->paginate($perPage)
            ->appends($request->query());

        $ids = collect($products->items())->pluck('id')->map(fn ($value) => (int) $value)->all();
        $historyIds = [];
        if ($ids !== []) {
            $historyIds = collect(['sale_items', 'inventory_receipt_items', 'stock_opname_items', 'stock_movements'])
                ->flatMap(fn ($table) => DB::table($table)->whereIn('product_id', $ids)->distinct()->pluck('product_id'))
                ->map(fn ($value) => (int) $value)
                ->unique()
                ->values()
                ->all();
        }
        foreach ($products->items() as $product) {
            $product->has_history = in_array((int) $product->id, $historyIds, true);
        }

        $summary = [
            'total' => DB::table('products')->count(),
            'active' => DB::table('products')->where('is_active', true)->count(),
            'inactive' => DB::table('products')->where('is_active', false)->count(),
            'low' => DB::table('products')->where('is_active', true)->where('stock_quantity', '>', 0)->whereColumn('stock_quantity', '<=', 'min_stock')->count(),
            'out' => DB::table('products')->where('is_active', true)->where('stock_quantity', '<=', 0)->count(),
        ];
        $categories = DB::table('products')->distinct()->orderBy('category')->pluck('category');
        $units = DB::table('products')->distinct()->orderBy('unit')->pluck('unit');
        $productOptions = [];
        foreach (['unit' => $units, 'category' => $categories] as $type => $usedNames) {
            $savedOptions = DB::table('product_options')->where('type', $type)->get();
            $productOptions[$type] = $savedOptions->pluck('name')->merge($usedNames)->unique()->sort()->values()
                ->map(fn (string $name): array => [
                    'name' => $name,
                    'id' => $savedOptions->firstWhere('name', $name)?->id,
                    'used' => $usedNames->contains($name),
                ])->all();
        }

        if (view()->exists('products.index')) {
            return view('products.index', compact('products', 'filters', 'search', 'perPage', 'summary', 'categories', 'units', 'productOptions'));
        }

        return response()->json($products);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string|max:50|unique:products,sku',
            'name' => 'required|string|max:255',
            'unit' => 'required|string|max:20',
            'category' => 'nullable|string|max:50',
            'cost_price' => ['required', 'regex:/^\d+(\.\d{1,4})?$/', 'numeric', 'min:0'],
            'selling_price' => ['required', 'regex:/^\d+(\.\d{1,2})?$/', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'regex:/^\d+(\.\d{1,4})?$/', 'numeric', 'min:0'],
            'is_active' => 'nullable|boolean',
        ]);

        // Opening inventory without an offset journal unbalances equity.
        $id = DB::table('products')->insertGetId([
            'sku' => trim($validated['sku']),
            'name' => trim($validated['name']),
            'unit' => trim($validated['unit']),
            'category' => trim((string) ($validated['category'] ?? 'Umum')) ?: 'Umum',
            'cost_price' => $this->decimal((string) $validated['cost_price'], 4),
            'selling_price' => $this->decimal((string) $validated['selling_price'], 2),
            'stock_quantity' => '0.0000',
            'min_stock' => $this->decimal((string) ($validated['min_stock'] ?? '0'), 4),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'id' => $id], 201);
        }

        return redirect()->route('products.index')->with('success', 'Produk berhasil didaftarkan dengan stok awal 0.');
    }

    public function storeOption(Request $request): JsonResponse
    {
        $type = $request->validate(['type' => ['required', Rule::in(['unit', 'category'])]])['type'];
        $request->merge(['name' => is_string($request->input('name')) ? trim($request->input('name')) : $request->input('name')]);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:'.($type === 'unit' ? 20 : 50), Rule::unique('product_options', 'name')->where('type', $type)],
        ]);
        $id = DB::table('product_options')->insertGetId([
            'type' => $type,
            'name' => $validated['name'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['id' => $id, 'name' => $validated['name'], 'used' => DB::table('products')->where($type, $validated['name'])->exists()], 201);
    }

    public function destroyOption(int $id): JsonResponse
    {
        return DB::transaction(function () use ($id): JsonResponse {
            $option = DB::table('product_options')->where('id', $id)->lockForUpdate()->first();
            abort_if($option === null, 404);
            if (DB::table('products')->where($option->type, $option->name)->exists()) {
                return response()->json(['message' => 'Pilihan masih digunakan oleh produk dan tidak dapat dihapus.'], 422);
            }
            DB::table('product_options')->where('id', $id)->delete();

            return response()->json(['success' => true]);
        });
    }

    public function update(Request $request, $id)
    {
        $product = DB::table('products')->where('id', $id)->first();
        if (! $product) {
            abort(404);
        }

        $validated = $request->validate([
            'sku' => 'required|string|max:50|unique:products,sku,'.$id,
            'name' => 'required|string|max:255',
            'unit' => 'required|string|max:20',
            'category' => 'nullable|string|max:50',
            'cost_price' => ['required', 'regex:/^\d+(\.\d{1,4})?$/', 'numeric', 'min:0'],
            'selling_price' => ['required', 'regex:/^\d+(\.\d{1,2})?$/', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'regex:/^\d+(\.\d{1,4})?$/', 'numeric', 'min:0'],
            'is_active' => 'nullable|boolean',
        ]);

        // stock_quantity intentionally remains governed by receipts/opname.
        DB::table('products')->where('id', $id)->update([
            'sku' => trim($validated['sku']),
            'name' => trim($validated['name']),
            'unit' => trim($validated['unit']),
            'category' => trim((string) ($validated['category'] ?? $product->category)) ?: 'Umum',
            'cost_price' => $this->decimal((string) $validated['cost_price'], 4),
            'selling_price' => $this->decimal((string) $validated['selling_price'], 2),
            'min_stock' => $this->decimal((string) ($validated['min_stock'] ?? $product->min_stock), 4),
            'is_active' => array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : (bool) $product->is_active,
            'updated_at' => now(),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'id' => (int) $id]);
        }

        return redirect()->route('products.index')->with('success', 'Produk berhasil diperbarui.');
    }

    public function destroy(Request $request, $id)
    {
        if (! DB::table('products')->where('id', $id)->exists()) {
            abort(404);
        }

        $hasHistory = DB::table('sale_items')->where('product_id', $id)->exists()
            || DB::table('inventory_receipt_items')->where('product_id', $id)->exists()
            || DB::table('stock_opname_items')->where('product_id', $id)->exists()
            || DB::table('stock_movements')->where('product_id', $id)->exists();

        if ($hasHistory) {
            $message = 'Produk tidak dapat dihapus karena sudah memiliki riwayat transaksi/stok.';
            if ($request->wantsJson()) {
                return response()->json(['error' => $message], 422);
            }

            return redirect()->route('products.index')->with('error', $message);
        }

        DB::table('products')->where('id', $id)->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('products.index')->with('success', 'Produk berhasil dihapus.');
    }

    private function decimal(string $value, int $scale): string
    {
        return (string) BigDecimal::of($value)->toScale($scale, RoundingMode::HalfUp);
    }
}
