<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Services\V2\CustomerLookup;
use App\Services\V2\DeliveryTrips;
use App\Services\V2\MasterData;
use App\Services\V2\MasterInsights;
use App\Services\V2\MasterRelations;
use App\Services\V2\Money;
use App\Services\V2\MonitorMetrics;
use App\Services\V2\OpeningBalances;
use App\Services\V2\Operations;
use App\Services\V2\PosDashboard;
use App\Services\V2\ReportsDocuments;
use App\Services\V2\SupplyTrips;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class WorkspaceController extends Controller
{
    public function __construct(private Operations $operations) {}

    public function index(Request $request)
    {
        $role = $this->operations->role($request->user()->id);
        if (config('v2.enabled') && $request->routeIs('v2.index')) {
            return redirect()->route('pos.index', $request->query());
        }

        $shifts = DB::table('v2_shifts as s')->leftJoin('users as u', 'u.id', '=', 's.user_id')
            ->when($role !== 'admin', fn ($q) => $q->where('s.user_id', $request->user()->id))
            ->orderByDesc('s.id')->limit(30)->get(['s.*', 'u.name as user_name']);
        $invoices = DB::table('v2_invoices as i')->leftJoin('v2_parties as p', 'p.id', '=', 'i.party_id')
            ->orderByDesc('i.id')->limit(100)->get(['i.*', 'p.name as party_name']);
        $payments = DB::table('v2_payments as pm')
            ->leftJoin('v2_invoices as i', 'i.id', '=', 'pm.invoice_id')
            ->leftJoin('v2_parties as p', 'p.id', '=', 'i.party_id')
            ->leftJoin('users as u', 'u.id', '=', 'pm.requested_by')
            ->when($role !== 'admin', fn ($q) => $q->where('pm.requested_by', $request->user()->id))
            ->orderByDesc('pm.id')->limit(50)->get(['pm.*', 'i.number as invoice_number', 'i.direction as invoice_direction', 'p.name as party_name', 'u.name as requester_name']);
        $products = DB::table('products')->where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name', 'category', 'selling_price', 'contractor_price', 'stock_quantity', 'min_stock', 'unit']);
        $customers = DB::table('v2_parties')->where('kind', 'customer')->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'notes']);
        $sales = DB::table('v2_sales')->when($role !== 'admin', fn ($q) => $q->where('user_id', $request->user()->id))->orderByDesc('id')->limit(30)->get();
        $deliveries = DB::table('v2_deliveries as d')->leftJoin('vehicles as v', 'v.id', '=', 'd.vehicle_id')
            ->whereIn('d.sale_id', $sales->pluck('id'))->orderByDesc('d.id')->get(['d.*', 'v.license_plate']);
        $suppliers = $role === 'admin' ? DB::table('v2_parties')->where('kind', 'supplier')->where('is_active', true)->get() : collect();
        $dispatchVehicles = DB::table('vehicles')->where('status', 'active')->get();
        $vehicles = $role === 'admin' ? $dispatchVehicles : collect();
        $drivers = DB::table('v2_drivers as drivers')
            ->leftJoin('v2_parties as employees', 'employees.id', '=', 'drivers.employee_id')
            ->where('drivers.is_active', true)
            ->where(fn ($query) => $query->where('drivers.kind', 'external')->orWhere('employees.is_active', true))
            ->orderBy('drivers.name')->get(['drivers.*', 'employees.name as employee_name']);
        $productOptions = $role === 'admin' ? DB::table('product_options')->orderBy('name')->get()->groupBy('type') : collect();
        $employees = $role === 'admin' ? DB::table('v2_parties')->where('kind', 'employee')->orderBy('name')->get() : collect();
        $productSupplierLinks = $role === 'admin' ? DB::table('v2_product_suppliers')
            ->join('v2_parties', 'v2_parties.id', '=', 'v2_product_suppliers.supplier_id')
            ->orderBy('v2_parties.name')->get(['v2_product_suppliers.*', 'v2_parties.name', 'v2_parties.is_active'])->groupBy('product_id') : collect();
        $transportDeliveries = $role === 'admin' ? DB::table('v2_deliveries')->orderByDesc('id')->get() : collect();
        $receiptHistory = $role === 'admin' ? DB::table('inventory_receipts')->orderByDesc('receipt_date')->orderByDesc('id')->limit(50)->get() : collect();
        // Rincian barang per penerimaan untuk tabel Riwayat (baca saja; satu kueri untuk 50 nota terakhir).
        $receiptItems = $receiptHistory->isEmpty() ? collect() : DB::table('inventory_receipt_items')
            ->join('products', 'products.id', '=', 'inventory_receipt_items.product_id')
            ->whereIn('inventory_receipt_items.inventory_receipt_id', $receiptHistory->pluck('id'))
            ->orderBy('inventory_receipt_items.id')
            ->get(['inventory_receipt_items.inventory_receipt_id', 'inventory_receipt_items.quantity', 'inventory_receipt_items.unit_cost', 'inventory_receipt_items.subtotal', 'products.name as product_name', 'products.unit as product_unit'])
            ->groupBy('inventory_receipt_id');
        $relationRecords = [];
        if ($role === 'admin') {
            foreach (['driver', 'unit', 'category'] as $kind) {
                $relationRecords[$kind] = app(MasterRelations::class)->records($request->user()->id, $kind);
            }
        }
        $masterKind = $request->query('master_kind', 'product');
        abort_unless(is_string($masterKind) && in_array($masterKind, app(MasterData::class)->kinds(), true), 422);
        $masterRecords = $role === 'admin' ? app(MasterData::class)->records($request->user()->id, $masterKind) : collect();

        $allMasterRecords = [];
        if ($role === 'admin') {
            $masterService = app(MasterData::class);
            foreach ($masterService->kinds() as $k) {
                $allMasterRecords[$k] = $masterService->records($request->user()->id, $k);
            }
        }
        // Rincian relasi master: produk yang dipasok tiap supplier, dan riwayat pemakaian tiap mobil.
        $insights = app(MasterInsights::class);
        $supplierInsights = $role === 'admin'
            ? $insights->suppliers(collect($allMasterRecords['supplier'] ?? [])->pluck('id')->map(fn ($id): int => (int) $id)->all())
            : [];
        $vehicleInsights = $role === 'admin'
            ? $insights->vehicles(collect($allMasterRecords['vehicle'] ?? [])->pluck('id')->map(fn ($id): int => (int) $id)->all())
            : [];
        // Dashboard kasir dihitung di layanan (cursor + BigDecimal), bukan di Blade.
        $posDashboard = app(PosDashboard::class)->build($role === 'admin' ? null : (int) $request->user()->id);
        $date = $request->query('date', now()->toDateString());
        abort_unless(is_string($date), 422);
        $daily = app(ReportsDocuments::class)->daily($request->user()->id, $date);
        $metrics = app(MonitorMetrics::class)->snapshot(now()->toDateString(), $role === 'admin', $request->user()->id);
        // Ringkasan admin (Pengawasan): kas laci shift terbuka, saldo bank buku besar, piutang lewat tempo, stok yang perlu dipantau.
        $adminSummary = $role === 'admin' ? app(\App\Services\V2\AdminSummary::class)->snapshot(now()->toDateString()) : null;
        $driverAdvances = $role === 'admin' ? DB::table('v2_driver_advances')->where('status', 'open')->orderByDesc('id')->get() : collect();

        foreach (['sale_search', 'sale_method', 'start_date', 'end_date', 'history_per_page', 'history_page'] as $param) {
            if ($request->has($param) && is_array($request->query($param))) {
                abort(422);
            }
        }

        $saleSearch = (string) $request->query('sale_search', '');
        abort_if(strlen($saleSearch) > 100, 422);

        $saleMethod = (string) $request->query('sale_method', '');
        abort_unless(in_array($saleMethod, ['', 'cash', 'transfer', 'credit'], true), 422);

        $startDate = $request->query('start_date');
        if ($startDate !== null && $startDate !== '') {
            abort_unless(is_string($startDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) && checkdate((int) substr($startDate, 5, 2), (int) substr($startDate, 8, 2), (int) substr($startDate, 0, 4)), 422);
        } else {
            $startDate = null;
        }

        $endDate = $request->query('end_date');
        if ($endDate !== null && $endDate !== '') {
            abort_unless(is_string($endDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate) && checkdate((int) substr($endDate, 5, 2), (int) substr($endDate, 8, 2), (int) substr($endDate, 0, 4)), 422);
        } else {
            $endDate = null;
        }

        if ($startDate !== null && $endDate !== null) {
            abort_if($endDate < $startDate, 422);
        }

        $rawPerPage = $request->query('history_per_page', 10);
        abort_unless(in_array((string) $rawPerPage, ['10', '25', '50'], true), 422);
        $historyPerPage = (int) $rawPerPage;

        $rawPage = $request->query('history_page', 1);
        abort_unless(filter_var($rawPage, FILTER_VALIDATE_INT) !== false && (int) $rawPage >= 1, 422);
        $historyPage = (int) $rawPage;

        $historyFilters = [
            'sale_search' => $saleSearch,
            'sale_method' => $saleMethod,
            'start_date' => $startDate ?? '',
            'end_date' => $endDate ?? '',
            'history_per_page' => $historyPerPage,
            'history_page' => $historyPage,
        ];

        $hasDeliverySub = DB::table('v2_deliveries')
            ->selectRaw('1')
            ->whereColumn('v2_deliveries.sale_id', 'v2_sales.id')
            ->limit(1);

        $historyQuery = DB::table('v2_sales')
            ->select('v2_sales.*')
            ->selectSub($hasDeliverySub, 'has_delivery')
            ->when($role !== 'admin', fn ($q) => $q->where('v2_sales.user_id', $request->user()->id));

        if ($saleSearch !== '') {
            $historyQuery->where('v2_sales.number', 'like', '%'.$saleSearch.'%');
        }

        if ($saleMethod !== '') {
            $historyQuery->where('v2_sales.payment_method', $saleMethod);
        }

        if ($startDate !== null) {
            $historyQuery->whereDate('v2_sales.created_at', '>=', $startDate);
        }

        if ($endDate !== null) {
            $historyQuery->whereDate('v2_sales.created_at', '<=', $endDate);
        }

        $historySales = $historyQuery
            ->orderByDesc('v2_sales.id')
            ->paginate($historyPerPage, ['*'], 'history_page', $historyPage)
            ->appends($request->query())
            ->fragment('kasir');

        return view('v2.workspace', compact('adminSummary', 'role', 'shifts', 'invoices', 'payments', 'products', 'customers', 'sales', 'deliveries', 'suppliers', 'vehicles', 'daily', 'driverAdvances', 'dispatchVehicles', 'drivers', 'employees', 'productOptions', 'productSupplierLinks', 'supplierInsights', 'vehicleInsights', 'posDashboard', 'transportDeliveries', 'receiptHistory', 'receiptItems', 'metrics', 'relationRecords', 'masterKind', 'masterRecords', 'allMasterRecords', 'historySales', 'historyFilters'));
    }

    public function saveRelation(Request $request, string $kind): JsonResponse
    {
        return $this->run(fn () => ['id' => app(MasterRelations::class)->store($request->user()->id, $kind, $request->all())]);
    }

    public function updateRelation(Request $request, string $kind, int $id): JsonResponse
    {
        return $this->run(function () use ($request, $kind, $id): array {
            app(MasterRelations::class)->update($request->user()->id, $kind, $id, $request->all());

            return ['status' => 'updated'];
        });
    }

    public function archiveRelation(Request $request, string $kind, int $id): JsonResponse
    {
        return $this->run(function () use ($request, $kind, $id): array {
            app(MasterRelations::class)->archive($request->user()->id, $kind, $id);

            return ['status' => 'archived'];
        });
    }

    public function master(Request $request, string $kind): JsonResponse
    {
        $this->operations->requireRole($request->user()->id, ['admin']);
        abort_unless(in_array($kind, app(MasterData::class)->kinds(), true), 404);

        return response()->json(['records' => app(MasterData::class)->records($request->user()->id, $kind)]);
    }

    public function saveMaster(Request $request, string $kind): JsonResponse
    {
        $this->operations->requireRole($request->user()->id, ['admin']);
        abort_unless(in_array($kind, app(MasterData::class)->kinds(), true), 404);

        return $this->run(fn () => ['id' => app(MasterData::class)->store($request->user()->id, $kind, $request->all())]);
    }

    /** Saran pelanggan saat kasir mengetik nama pembeli. */
    public function searchCustomers(Request $request): JsonResponse
    {
        $this->operations->requireRole($request->user()->id, ['admin', 'cashier']);
        $data = $request->validate(['q' => ['required', 'string', 'max:255']]);

        return response()->json(['results' => app(CustomerLookup::class)->search($data['q'])]);
    }

    /** Memakai pelanggan yang sudah ada, atau membuatnya bila nama itu belum terdaftar. */
    public function resolveCustomer(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);

        return $this->run(fn () => app(CustomerLookup::class)->resolve($request->user()->id, $data['name']));
    }

    public function openShift(Request $request): JsonResponse
    {
        $data = $request->validate(['cash' => ['required', 'string', 'max:30'], 'source' => ['required', Rule::in(['admin', 'carry', 'both'])], 'carry_from' => ['nullable', 'integer', 'min:1']]);

        return $this->run(fn () => ['id' => $this->operations->openShift($request->user()->id, $data['cash'], $data['source'], $data['carry_from'] ?? null)]);
    }

    public function closeShift(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['counted' => ['required', 'string', 'max:30']]);

        return $this->run(fn () => $this->operations->closeShift($request->user()->id, $id, $data['counted']));
    }

    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'string', 'max:30'],
            'items.*.discount' => ['sometimes', 'array:type,value'],
            'items.*.discount.type' => ['required_with:items.*.discount', Rule::in(['amount', 'percent'])],
            'items.*.discount.value' => ['required_with:items.*.discount', 'string', 'max:30'],
            'discount' => ['sometimes', 'array:type,value'],
            'discount.type' => ['required_with:discount', Rule::in(['amount', 'percent'])],
            'discount.value' => ['required_with:discount', 'string', 'max:30'],
            'delivery_fee' => ['required', 'string', 'max:30'],
            'customer_id' => ['nullable', 'integer', 'min:1'],
            'payment_method' => ['required', Rule::in(['cash', 'transfer', 'credit'])],
            'deposit_method' => ['nullable', Rule::in(['cash', 'transfer'])],
            'paid_amount' => ['required', 'string', 'max:30'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:100'],
            'fulfillment' => ['required', Rule::in(['pickup', 'delivery'])],
            'destination' => ['nullable', 'required_if:fulfillment,delivery', 'string', 'max:255'],
            'price_tier' => ['nullable', Rule::in(['retail', 'contractor'])],
        ]);

        return $this->run(fn () => $this->operations->checkout($request->user()->id, $data));
    }

    public function collect(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['amount' => ['required', 'string', 'max:30'], 'method' => ['required', Rule::in(['cash', 'transfer'])], 'idempotency_key' => ['required', 'string', 'min:8', 'max:100'], 'reference' => ['nullable', 'string', 'max:255']]);

        return $this->run(fn () => ['id' => $this->operations->collect($request->user()->id, $id, $data['amount'], $data['method'], $data['idempotency_key'], $data['reference'] ?? null)]);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        return $this->run(function () use ($request, $id): array {
            $this->operations->approvePayment($request->user()->id, $id);

            return ['id' => $id, 'status' => 'posted'];
        });
    }

    public function resolveShift(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);

        return $this->run(function () use ($request, $id, $data): array {
            $this->operations->resolveShift($request->user()->id, $id, $data['reason']);

            return ['status' => 'closed'];
        });
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);

        return $this->run(function () use ($request, $id, $data): array {
            $this->operations->rejectPayment($request->user()->id, $id, $data['reason']);

            return ['status' => 'rejected'];
        });
    }

    public function template(Request $request, string $kind)
    {
        $bytes = app(MasterData::class)->template($request->user()->id, $kind);

        return response($bytes)->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')->header('Content-Disposition', 'attachment; filename="master-'.$kind.'.xlsx"')->header('Cache-Control', 'private, no-store');
    }

    public function importPreview(Request $request, string $kind): JsonResponse
    {
        $this->operations->requireRole($request->user()->id, ['admin']);
        $request->validate(['file' => ['required', 'file', 'max:2048']]);

        return $this->run(fn () => app(MasterData::class)->preview($request->user()->id, $kind, $request->file('file')->getRealPath()));
    }

    public function importCommit(Request $request, int $id): JsonResponse
    {
        return $this->run(fn () => app(MasterData::class)->commit($request->user()->id, $id));
    }

    public function receive(Request $request): JsonResponse
    {
        $this->operations->requireRole($request->user()->id, ['admin']);

        return $this->run(fn () => app(SupplyTrips::class)->receive($request->user()->id, $request->all()));
    }

    public function trip(Request $request): JsonResponse
    {
        $this->operations->requireRole($request->user()->id, ['admin']);

        return $this->run(fn () => app(SupplyTrips::class)->trip($request->user()->id, $request->all()));
    }

    public function updateMaster(Request $request, string $kind, int $id): JsonResponse
    {
        return $this->run(function () use ($request, $kind, $id): array {
            app(MasterData::class)->update($request->user()->id, $kind, $id, $request->all());

            return ['status' => 'updated'];
        });
    }

    public function archiveMaster(Request $request, string $kind, int $id): JsonResponse
    {
        return $this->run(function () use ($request, $kind, $id): array {
            app(MasterData::class)->archive($request->user()->id, $kind, $id);

            return ['status' => 'archived'];
        });
    }

    public function openingPreview(Request $request): JsonResponse
    {
        return $this->run(fn () => app(OpeningBalances::class)->preview($request->user()->id, $request->all()));
    }

    public function document(Request $request, int $id, string $kind)
    {
        $document = app(ReportsDocuments::class)->document($request->user()->id, $id, $kind);
        $invoice = DB::table('v2_invoices')->where('sale_id', $id)->first();
        $document['paid'] = (string) Money::value((string) ($invoice->paid ?? '0'));
        $document['remaining'] = (string) Money::value($document['sale']['total'])->minus($document['paid']);
        $paymentHistory = DB::table('v2_payments')->join('v2_invoices', 'v2_invoices.id', '=', 'v2_payments.invoice_id')->where('v2_invoices.sale_id', $id)->get(['v2_payments.*']);

        return response()->view('v2.document', compact('document', 'paymentHistory'))->header('Cache-Control', 'private, no-store');
    }

    public function opening(Request $request): JsonResponse
    {
        $request->validate(['idempotency_key' => ['required', 'string', 'min:8', 'max:100'], 'inventory' => ['sometimes', 'array', 'max:1000'], 'receivables' => ['sometimes', 'array', 'max:1000'], 'payables' => ['sometimes', 'array', 'max:1000']]);

        return $this->run(fn () => app(OpeningBalances::class)->post($request->user()->id, $request->all()));
    }

    public function dispatch(Request $request, int $id): JsonResponse
    {
        return $this->run(function () use ($request, $id): array {
            app(DeliveryTrips::class)->dispatch($request->user()->id, $id, $request->all());

            return ['status' => 'sent'];
        });
    }

    public function receiveDelivery(Request $request, int $id): JsonResponse
    {
        return $this->run(function () use ($request, $id): array {
            app(DeliveryTrips::class)->receive($request->user()->id, $id);

            return ['status' => 'received'];
        });
    }

    public function advance(Request $request): JsonResponse
    {
        return $this->run(fn () => app(DeliveryTrips::class)->advance($request->user()->id, $request->all()));
    }

    public function settle(Request $request, int $id): JsonResponse
    {
        return $this->run(fn () => app(DeliveryTrips::class)->settle($request->user()->id, $id, $request->all()));
    }

    private function run(callable $action): JsonResponse
    {
        try {
            return response()->json($action());
        } catch (HttpExceptionInterface $e) {
            throw $e;
        } catch (InvalidArgumentException|RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
