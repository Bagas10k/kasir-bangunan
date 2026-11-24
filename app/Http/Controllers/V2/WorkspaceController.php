<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Services\V2\AdminSummary;
use App\Services\V2\BankAccounts;
use App\Services\V2\CustomerLookup;
use App\Services\V2\DeliveryTrips;
use App\Services\V2\MasterData;
use App\Services\V2\MasterInsights;
use App\Services\V2\MasterRelations;
use App\Services\V2\Money;
use App\Services\V2\MonitorMetrics;
use App\Services\V2\OpeningBalances;
use App\Services\V2\Operations;
use App\Services\V2\PaymentProofs;
use App\Services\V2\PosDashboard;
use App\Services\V2\ReceiptDrafts;
use App\Services\V2\ReportsDocuments;
use App\Services\V2\ShiftClosingSummary;
use App\Services\V2\SupplyTrips;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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
            ->when($role !== 'admin', fn ($q) => $q->where(fn ($visible) => $visible->where('s.is_shared', true)->orWhere('s.user_id', $request->user()->id)))
            ->orderByDesc('s.id')->limit(30)->get(['s.*', 'u.name as user_name']);
        $reviewShifts = $role === 'admin'
            ? DB::table('v2_shifts')->where('status', 'review')->orderBy('closed_at')->orderBy('id')->get(['id', 'opened_at', 'counted_cash', 'difference'])
            : collect();
        $activeStoreShift = $this->operations->activeStoreShift();
        $todayCashDay = $activeStoreShift ? null : $this->todayCashDay();
        ['invoices' => $invoices, 'invoiceFilters' => $invoiceFilters] = $this->invoiceData($request);
        $payments = DB::table('v2_payments as pm')
            ->leftJoin('v2_invoices as i', 'i.id', '=', 'pm.invoice_id')
            ->leftJoin('v2_parties as p', 'p.id', '=', 'i.party_id')
            ->leftJoin('users as u', 'u.id', '=', 'pm.requested_by')
            ->leftJoin('v2_payment_proofs as proof', 'proof.payment_id', '=', 'pm.id')
            ->when($role !== 'admin', fn ($q) => $q->where('pm.requested_by', $request->user()->id))
            ->orderByDesc('pm.id')->limit(50)->get(['pm.*', 'i.number as invoice_number', 'i.direction as invoice_direction', 'p.name as party_name', 'u.name as requester_name', 'proof.id as proof_id']);
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
        $receiptHistory = $role === 'admin' ? DB::table('inventory_receipts')->leftJoin('users as void_user', 'void_user.id', '=', 'inventory_receipts.voided_by')
            ->orderByDesc('inventory_receipts.receipt_date')->orderByDesc('inventory_receipts.id')->limit(50)
            ->get(['inventory_receipts.*', 'void_user.name as voided_by_name']) : collect();
        // Dokumen yang sudah diposting selalu final. Draf disimpan terpisah dan belum masuk stok/utang.
        $receiptDrafts = $role === 'admin' && Schema::hasTable('v2_receipt_drafts')
            ? DB::table('v2_receipt_drafts as d')->join('v2_parties as p', 'p.id', '=', 'd.supplier_id')
                ->leftJoin('users as u', 'u.id', '=', 'd.created_by')
                ->whereIn('d.status', ['draft', 'cancelled'])->orderByDesc('d.id')->limit(50)
                ->get(['d.*', 'p.name as supplier_name', 'u.name as author_name'])
            : collect();
        foreach ($receiptDrafts as $draft) {
            $draft->items = json_decode($draft->items, true, 512, JSON_THROW_ON_ERROR);
        }
        $draftProducts = $receiptDrafts->isEmpty() ? collect() : DB::table('products')
            ->whereIn('id', $receiptDrafts->flatMap(fn ($draft) => array_column($draft->items, 'product_id'))->unique())
            ->get(['id', 'name', 'sku', 'unit', 'cost_price', 'stock_quantity'])->keyBy('id');
        $receiptFinalAudit = $role === 'admin' && Schema::hasTable('v2_receipt_drafts')
            ? DB::table('v2_receipt_drafts as d')->leftJoin('users as u', 'u.id', '=', 'd.confirmed_by')
                ->whereIn('d.receipt_id', $receiptHistory->pluck('id'))->where('d.status', 'confirmed')
                ->get(['d.receipt_id', 'd.confirmed_at', 'u.name as confirmed_by_name'])->keyBy('receipt_id')
            : collect();
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
        $customerInsights = $role === 'admin'
            ? $insights->customers(collect($allMasterRecords['customer'] ?? [])->pluck('id')->map(fn ($id): int => (int) $id)->all())
            : [];
        $supplierInsights = $role === 'admin'
            ? $insights->suppliers(collect($allMasterRecords['supplier'] ?? [])->pluck('id')->map(fn ($id): int => (int) $id)->all())
            : [];
        $vehicleInsights = $role === 'admin'
            ? $insights->vehicles(collect($allMasterRecords['vehicle'] ?? [])->pluck('id')->map(fn ($id): int => (int) $id)->all())
            : [];
        // Dashboard kasir dihitung di layanan (cursor + BigDecimal), bukan di Blade.
        $posDashboard = app(PosDashboard::class)->build(null);
        $freightReady = Schema::hasColumn('v2_receipt_drafts', 'freight_amount') && Schema::hasColumn('inventory_receipts', 'freight_amount');
        $freightAccounts = $role === 'admin' ? DB::table('chart_of_accounts')->where('account_type', 'expense')->where('normal_balance', 'debit')->where('is_active', true)->orderBy('account_code')->get(['account_code', 'account_name']) : collect();
        $date = $request->query('date', now()->toDateString());
        abort_unless(is_string($date), 422);
        $daily = app(ReportsDocuments::class)->daily($request->user()->id, $date);
        $metrics = app(MonitorMetrics::class)->snapshot(now()->toDateString(), $role === 'admin', $request->user()->id);
        // Ringkasan admin (Pengawasan): kas laci shift terbuka, saldo bank buku besar, piutang lewat tempo, stok yang perlu dipantau.
        $adminSummary = $role === 'admin' ? app(AdminSummary::class)->snapshot(now()->toDateString()) : null;
        $driverAdvances = $role === 'admin' ? DB::table('v2_driver_advances')->where('status', 'open')->orderByDesc('id')->get() : collect();

        ['historySales' => $historySales, 'historyFilters' => $historyFilters] = $this->historyData($request);
        $recentSales = DB::table('v2_sales as s')->leftJoin('v2_parties as p', 'p.id', '=', 's.customer_id')
            ->orderByDesc('s.id')->limit(5)->get(['s.id', 's.number', 'p.name as customer_name']);
        $bankService = app(BankAccounts::class);
        $bankAccounts = $bankService->active();
        $bankAccountNames = $bankService->nameMap(false);

        return view('v2.workspace', compact('activeStoreShift', 'todayCashDay', 'adminSummary', 'role', 'shifts', 'reviewShifts', 'invoices', 'invoiceFilters', 'payments', 'products', 'customers', 'sales', 'deliveries', 'suppliers', 'vehicles', 'daily', 'driverAdvances', 'dispatchVehicles', 'drivers', 'employees', 'productOptions', 'productSupplierLinks', 'supplierInsights', 'customerInsights', 'vehicleInsights', 'posDashboard', 'transportDeliveries', 'receiptHistory', 'receiptItems', 'receiptDrafts', 'draftProducts', 'receiptFinalAudit', 'metrics', 'relationRecords', 'masterKind', 'masterRecords', 'allMasterRecords', 'historySales', 'historyFilters', 'recentSales', 'freightReady', 'freightAccounts', 'bankAccounts', 'bankAccountNames'));
    }

    public function dashboardPeriod(Request $request): JsonResponse
    {
        $this->operations->requireRole($request->user()->id, ['admin', 'cashier']);
        $role = $this->operations->role($request->user()->id);
        $data = $request->validate(['from' => ['required', 'string'], 'to' => ['required', 'string']]);
        $period = app(PosDashboard::class)->custom($data['from'], $data['to']);
        $html = view('v2.pos-monitor', [
            'posDashboard' => ['custom' => $period],
            'products' => DB::table('products')->where('is_active', true)->get(['stock_quantity', 'min_stock']),
            'role' => $role, 'activeStoreShift' => null, 'todayCashDay' => null, 'recentSales' => collect(),
        ])->render();

        return response()->json(['html' => $html])->header('Cache-Control', 'private, no-store');
    }

    public function invoices(Request $request): JsonResponse
    {
        $this->operations->requireRole($request->user()->id, ['admin', 'cashier']);
        $data = $this->invoiceData($request);

        return response()->json([
            'html' => view('v2.invoice-results', $data)->render(),
            'filters' => $data['invoiceFilters'],
            'total' => $data['invoices']->total(),
        ])->header('Cache-Control', 'private, no-store');
    }

    /**
     * @return array{invoices: LengthAwarePaginator, invoiceFilters: array<string, int|string>, role: string}
     */
    private function invoiceData(Request $request): array
    {
        $validated = $request->validate([
            'invoice_direction' => ['sometimes', 'required', 'in:receivable,payable'],
            'invoice_status' => ['sometimes', 'required', 'in:unpaid,paid,all'],
            'invoice_search' => ['nullable', 'string', 'max:100'],
            'invoice_start' => ['nullable', 'date_format:Y-m-d'],
            'invoice_end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:invoice_start'],
            'invoice_per_page' => ['sometimes', 'required', 'integer', 'in:10,25,50'],
            'invoice_page' => ['sometimes', 'required', 'integer', 'min:1', 'max:1000000'],
        ]);
        $invoiceFilters = [
            'invoice_direction' => $validated['invoice_direction'] ?? 'receivable',
            'invoice_status' => $validated['invoice_status'] ?? 'unpaid',
            'invoice_search' => trim($validated['invoice_search'] ?? ''),
            'invoice_start' => $validated['invoice_start'] ?? '',
            'invoice_end' => $validated['invoice_end'] ?? '',
            'invoice_per_page' => (int) ($validated['invoice_per_page'] ?? 10),
            'invoice_page' => (int) ($validated['invoice_page'] ?? 1),
        ];
        $query = DB::table('v2_invoices as i')
            ->leftJoin('v2_parties as p', 'p.id', '=', 'i.party_id')
            ->where('i.direction', $invoiceFilters['invoice_direction'])
            ->when($invoiceFilters['invoice_status'] !== 'all', fn ($q) => $q->whereColumn('i.paid', $invoiceFilters['invoice_status'] === 'paid' ? '>=' : '<', 'i.amount'))
            ->when($invoiceFilters['invoice_search'] !== '', fn ($q) => $q->where(fn ($search) => $search
                ->where('i.number', 'like', '%'.$invoiceFilters['invoice_search'].'%')
                ->orWhere('p.name', 'like', '%'.$invoiceFilters['invoice_search'].'%')))
            ->when($invoiceFilters['invoice_start'] !== '', fn ($q) => $q->whereDate('i.created_at', '>=', $invoiceFilters['invoice_start']))
            ->when($invoiceFilters['invoice_end'] !== '', fn ($q) => $q->whereDate('i.created_at', '<=', $invoiceFilters['invoice_end']))
            ->select(['i.*', 'p.name as party_name'])
            ->selectSub(fn ($q) => $q->from('v2_payments')->whereColumn('v2_payments.invoice_id', 'i.id')->where('v2_payments.status', '<>', 'rejected')->selectRaw('coalesce(max(v2_payments.id), 0)'), 'last_payment_id');
        $invoices = $query->orderByDesc('i.id')
            ->paginate($invoiceFilters['invoice_per_page'], ['*'], 'invoice_page', $invoiceFilters['invoice_page']);
        $invoices->withPath(route(config('v2.enabled') ? 'pos.index' : 'v2.index'))
            ->appends($invoiceFilters)->fragment('tagihan');
        $paymentColumns = ['pm.id', 'proof.id as proof_id', 'pm.invoice_id', 'pm.amount', 'pm.method', 'pm.status', 'pm.reference', 'pm.created_at',
            'pm.resolution_note', 'pm.resolved_at', 'requester.name as requester_name',
            'approver.name as approver_name', 'resolver.name as resolver_name'];
        if (Schema::hasColumn('v2_payments', 'bank_account_code')) {
            $paymentColumns[] = 'pm.bank_account_code';
        }
        $invoicePayments = DB::table('v2_payments as pm')
            ->leftJoin('users as requester', 'requester.id', '=', 'pm.requested_by')
            ->leftJoin('users as approver', 'approver.id', '=', 'pm.approved_by')
            ->leftJoin('users as resolver', 'resolver.id', '=', 'pm.resolved_by')
            ->leftJoin('v2_payment_proofs as proof', 'proof.payment_id', '=', 'pm.id')
            ->whereIn('pm.invoice_id', $invoices->pluck('id'))
            ->orderBy('pm.id')
            ->get($paymentColumns)
            ->groupBy('invoice_id');
        foreach ($invoices as $invoice) {
            $invoice->payment_history = $invoicePayments->get($invoice->id, collect());
        }
        $role = $this->operations->role($request->user()->id);
        $bankService = app(BankAccounts::class);
        $bankAccounts = $bankService->active();
        $bankAccountNames = $bankService->nameMap(false);

        return compact('invoices', 'invoiceFilters', 'role', 'bankAccounts', 'bankAccountNames');
    }

    public function history(Request $request): JsonResponse
    {
        $this->operations->requireRole($request->user()->id, ['admin', 'cashier']);
        $data = $this->historyData($request);

        return response()->json([
            'html' => view('v2.sales-history-results', $data)->render(),
            'filters' => $data['historyFilters'],

        ])->header('Cache-Control', 'private, no-store');
    }

    private function historyData(Request $request): array
    {
        foreach (['sale_search', 'sale_method', 'start_date', 'end_date', 'history_per_page', 'history_page', 'history_shift', 'history_period'] as $param) {
            if ($request->has($param) && is_array($request->query($param))) {
                abort(422);
            }
        }

        $saleSearch = (string) $request->query('sale_search', '');
        abort_if(strlen($saleSearch) > 100, 422);

        $saleMethod = (string) $request->query('sale_method', '');
        abort_unless(in_array($saleMethod, ['', 'cash', 'transfer', 'credit'], true), 422);
        $saleStatus = $request->query('sale_status', '');
        abort_unless(is_string($saleStatus) && in_array($saleStatus, ['', 'unpaid', 'partial', 'paid'], true), 422);

        $defaultPeriod = ! $request->has('start_date') && ! $request->has('end_date') ? 'week' : 'custom';
        $historyPeriod = $request->query('history_period', $defaultPeriod);
        abort_unless(is_string($historyPeriod) && in_array($historyPeriod, ['day', 'week', 'month', 'custom'], true), 422);
        $periodDays = ['day' => 0, 'week' => 6, 'month' => 29];
        $startDate = $historyPeriod === 'custom' ? $request->query('start_date') : now()->subDays($periodDays[$historyPeriod])->toDateString();
        if ($startDate !== null && $startDate !== '') {
            abort_unless(is_string($startDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) && checkdate((int) substr($startDate, 5, 2), (int) substr($startDate, 8, 2), (int) substr($startDate, 0, 4)), 422);
        } else {
            $startDate = null;
        }

        $endDate = $historyPeriod === 'custom' ? $request->query('end_date') : now()->toDateString();
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
            'history_period' => $historyPeriod,
            'sale_search' => $saleSearch,
            'sale_method' => $saleMethod,
            'sale_status' => $saleStatus,
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
            ->join('users', 'users.id', '=', 'v2_sales.user_id')
            ->leftJoin('v2_shifts as cash_day', 'cash_day.id', '=', 'v2_sales.shift_id')
            ->leftJoin('v2_parties as buyer', 'buyer.id', '=', 'v2_sales.customer_id')
            ->leftJoin('v2_invoices as invoice', 'invoice.sale_id', '=', 'v2_sales.id')
            ->select(['v2_sales.*', 'users.name as user_name', 'buyer.name as customer_name', 'invoice.paid as invoice_paid', 'invoice.amount as invoice_amount'])
            ->selectRaw('DATE(cash_day.opened_at) as cash_day')
            ->selectSub(fn ($q) => $q->from('v2_payments as pending')->whereColumn('pending.invoice_id', 'invoice.id')->where('pending.status', 'pending')->selectRaw('count(*)'), 'pending_count')
            ->selectSub($hasDeliverySub, 'has_delivery');

        if ($saleSearch !== '') {
            $historyQuery->where(fn ($q) => $q->where('v2_sales.number', 'like', '%'.$saleSearch.'%')->orWhere('buyer.name', 'like', '%'.$saleSearch.'%'));
        }
        if ($saleStatus !== '') {
            $historyQuery->where(function ($q) use ($saleStatus): void {
                $q->where(function ($invoice) use ($saleStatus): void {
                    $invoice->whereNotNull('invoice.id')->whereColumn('invoice.paid', $saleStatus === 'paid' ? '>=' : '<', 'invoice.amount');
                    if ($saleStatus === 'partial') {
                        $invoice->where('invoice.paid', '>', 0);
                    }
                })->orWhere(fn ($legacy) => $legacy->whereNull('invoice.id')->whereIn('v2_sales.status', $saleStatus === 'unpaid' ? ['unpaid', 'partial'] : [$saleStatus]));
            });
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
            ->withPath(route(config('v2.enabled') ? 'pos.index' : 'v2.index'))
            ->appends($historyFilters)
            ->fragment('riwayat-penjualan');

        return compact('historySales', 'historyFilters');
    }

    public function saleDetail(Request $request, int $id): JsonResponse
    {
        $this->operations->requireRole($request->user()->id, ['admin', 'cashier']);
        abort_unless(DB::table('v2_sales')->where('id', $id)->exists(), 404);
        $document = app(ReportsDocuments::class)->document($request->user()->id, $id);
        $inv = DB::table('v2_invoices as i')->leftJoin('v2_parties as p', 'p.id', '=', 'i.party_id')
            ->where('i.sale_id', $id)->select(['i.*', 'p.name as party_name'])
            ->selectSub(fn ($q) => $q->from('v2_payments')->whereColumn('invoice_id', 'i.id')->where('status', '<>', 'rejected')->selectRaw('coalesce(max(id), 0)'), 'last_payment_id')->first();
        $paymentHistory = $inv ? DB::table('v2_payments as pm')->leftJoin('users as u', 'u.id', '=', 'pm.requested_by')
            ->leftJoin('v2_payment_proofs as proof', 'proof.payment_id', '=', 'pm.id')
            ->where('pm.invoice_id', $inv->id)->orderBy('pm.id')->get(['pm.*', 'u.name as requester_name', 'proof.id as proof_id']) : collect();
        $role = $this->operations->role($request->user()->id);
        $bankService = app(BankAccounts::class);
        $bankAccounts = $bankService->active();
        $bankAccountNames = $bankService->nameMap(false);

        return response()->json(['sale_id' => $id, 'html' => view('v2.sale-detail', compact('document', 'inv', 'paymentHistory', 'role', 'bankAccounts', 'bankAccountNames'))->render()])
            ->header('Cache-Control', 'private, no-store');
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

    public function activateRelation(Request $request, string $kind, int $id): JsonResponse
    {
        return $this->run(function () use ($request, $kind, $id): array {
            app(MasterRelations::class)->activate($request->user()->id, $kind, $id);

            return ['status' => 'activated'];
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

    public function activeShift(Request $request): JsonResponse
    {
        $this->operations->requireRole($request->user()->id, ['admin', 'cashier']);
        $shift = $this->operations->activeStoreShift();

        return response()->json(['shift' => $shift ? [
            'id' => (int) $shift->id,
            'opened_at' => (string) $shift->opened_at,
            'opened_by' => (string) DB::table('users')->where('id', $shift->user_id)->value('name'),
        ] : null, 'today' => $shift ? null : $this->todayCashDay()])->header('Cache-Control', 'private, no-store');
    }

    private function todayCashDay(): ?object
    {
        return DB::table('v2_shifts')->whereDate('opened_at', now()->toDateString())
            ->orderByDesc('id')->first(['opened_at', 'status']);
    }

    public function closeShift(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['counted' => ['required', 'string', 'max:30'], 'fingerprint' => ['nullable', 'string', 'size:64']]);

        return $this->run(fn () => $this->operations->closeShift($request->user()->id, $id, $data['counted'], $data['fingerprint'] ?? null));
    }

    /** Rekap otomatis sebelum menutup shift: rincian penjualan dan perkiraan uang laci. */
    public function closingSummary(Request $request, int $id): JsonResponse
    {
        return $this->run(fn () => app(ShiftClosingSummary::class)->build($request->user()->id, $id));
    }

    public function checkout(Request $request): JsonResponse
    {
        $this->operations->requireRole($request->user()->id, ['admin', 'cashier']);
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
            'bank_account_code' => ['nullable', 'string', 'max:20'],
            'paid_amount' => ['required', 'string', 'max:30'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:100'],
            'fulfillment' => ['required', Rule::in(['pickup', 'delivery'])],
            'destination' => ['nullable', 'required_if:fulfillment,delivery', 'string', 'max:255'],
            'price_tier' => ['nullable', Rule::in(['retail', 'contractor'])],
        ]);

        $isTransfer = $data['payment_method'] === 'transfer' || ($data['payment_method'] === 'credit' && ($data['deposit_method'] ?? '') === 'transfer');
        $needsProof = $isTransfer && ! preg_match('/^0+(?:\.0+)?$/', trim($data['paid_amount']));
        $photo = $this->validateTransferProof($request, $needsProof);

        return $this->run(fn () => app(PaymentProofs::class)->record($request->user()->id, $photo, function () use ($request, $data): array {
            $result = $this->operations->checkout($request->user()->id, $data);
            $paymentId = DB::table('v2_payments')->where('invoice_id', $result['invoice_id'])
                ->where('idempotency_key', $data['idempotency_key'].':payment')->value('id');

            return ['payment_id' => $paymentId ? (int) $paymentId : null, 'data' => $result];
        }));
    }

    public function collect(Request $request, int $id): JsonResponse
    {
        $this->operations->requireRole($request->user()->id, ['admin', 'cashier']);
        $data = $request->validate(['amount' => ['required', 'string', 'max:30'], 'method' => ['required', Rule::in(['cash', 'transfer', 'main'])], 'bank_account_code' => ['nullable', 'string', 'max:20'], 'idempotency_key' => ['required', 'string', 'min:8', 'max:100'], 'reference' => ['nullable', 'string', 'max:255'], 'seen_payment_id' => ['nullable', 'integer', 'min:0'], 'confirm_duplicate' => ['nullable', 'boolean']]);
        $needsProof = $data['method'] === 'transfer' && DB::table('v2_invoices')->where('id', $id)->where('direction', 'receivable')->exists();
        $photo = $this->validateTransferProof($request, $needsProof);

        return $this->run(fn () => app(PaymentProofs::class)->record($request->user()->id, $photo, function () use ($request, $id, $data): array {
            $seen = isset($data['seen_payment_id']) ? (int) $data['seen_payment_id'] : null;
            $paymentId = $this->operations->collect($request->user()->id, $id, $data['amount'], $data['method'], $data['idempotency_key'], $data['reference'] ?? null, $seen, (bool) ($data['confirm_duplicate'] ?? false), $data['bank_account_code'] ?? null);

            return ['payment_id' => $paymentId, 'data' => ['id' => $paymentId, 'status' => (string) DB::table('v2_payments')->where('id', $paymentId)->value('status')]];
        }));
    }

    private function validateTransferProof(Request $request, bool $required): ?UploadedFile
    {
        $request->validate(['transfer_proof' => [$required ? 'required' : 'prohibited', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=8000,max_height=8000']], [
            'transfer_proof.required' => 'Unggah foto bukti transfer pelanggan terlebih dahulu.',
            'transfer_proof.image' => 'Bukti transfer harus berupa foto.',
            'transfer_proof.mimes' => 'Gunakan foto JPG, PNG, atau WebP.',
            'transfer_proof.max' => 'Ukuran foto maksimal 5 MB.',
            'transfer_proof.prohibited' => 'Foto bukti hanya untuk pembayaran transfer pelanggan.',
        ]);

        return $request->file('transfer_proof');
    }

    public function paymentProof(Request $request, int $id)
    {
        $this->operations->requireRole($request->user()->id, ['admin', 'cashier']);
        $proof = DB::table('v2_payment_proofs')->where('payment_id', $id)->first();
        abort_unless($proof && Storage::disk('local')->exists($proof->path), 404);

        return Storage::disk('local')->response($proof->path, 'bukti-transfer.'.$this->proofExtension($proof->mime_type), [
            'Content-Type' => $proof->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    private function proofExtension(string $mime): string
    {
        return ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? 'bin';
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

        return $this->run(fn () => app(ReceiptDrafts::class)->save($request->user()->id, $request->all()));
    }

    public function updateReceiptDraft(Request $request, int $id): JsonResponse
    {
        return $this->run(fn () => app(ReceiptDrafts::class)->save($request->user()->id, $request->all(), $id));
    }

    public function confirmReceiptDraft(Request $request, int $id): JsonResponse
    {
        $this->operations->requireRole($request->user()->id, ['admin']);
        $request->validate(['confirm_final' => ['required', 'accepted']]);

        return $this->run(fn () => app(ReceiptDrafts::class)->confirm($request->user()->id, $id, $request->all()));
    }

    public function cancelReceiptDraft(Request $request, int $id): JsonResponse
    {
        return $this->run(fn () => app(ReceiptDrafts::class)->cancel($request->user()->id, $id, $request->all()));
    }

    /** Jalur lama tetap ditolak di server: penerimaan yang sudah diposting terkunci. */
    public function voidReceipt(Request $request, int $id): JsonResponse
    {
        $this->operations->requireRole($request->user()->id, ['admin']);

        return $this->run(fn () => app(SupplyTrips::class)->voidReceipt($request->user()->id, $id, $request->all()));
    }

    /** Pemilih barang di form penerimaan: barang yang tertaut ke supplier terpilih. */
    public function supplierProducts(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        return $this->run(fn () => app(SupplyTrips::class)->productsFor($request->user()->id, $id, (string) ($data['q'] ?? '')));
    }

    public function restockProducts(Request $request): JsonResponse
    {
        $this->operations->requireRole($request->user()->id, ['admin']);
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['critical', 'out', 'low'])],
            'supplier_id' => ['nullable', 'integer', Rule::exists('v2_parties', 'id')->where('kind', 'supplier')->where('is_active', true)],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        return $this->run(fn () => app(SupplyTrips::class)->restockProducts(
            $request->user()->id, (string) ($data['q'] ?? ''), (string) ($data['status'] ?? 'critical'),
            isset($data['supplier_id']) ? (int) $data['supplier_id'] : null, (int) ($data['page'] ?? 1),
        ))->header('Cache-Control', 'private, no-store');
    }

    public function linkReceiptSupplier(Request $request): JsonResponse
    {
        $this->operations->requireRole($request->user()->id, ['admin']);
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
            'supplier_id' => ['nullable', 'integer', 'min:1', 'required_without:name', 'prohibits:name'],
            'name' => ['nullable', 'string', 'max:255', 'required_without:supplier_id', 'prohibits:supplier_id'],
            'notes' => ['nullable', 'string', 'max:255'],
            'confirmed' => ['required', 'accepted'],
            'idempotency_key' => ['required', 'string', 'max:100', 'regex:/^[\x20-\x7E]+$/'],
        ]);

        return $this->run(fn () => app(SupplyTrips::class)->linkReceiptSupplier($request->user()->id, $data))
            ->header('Cache-Control', 'private, no-store');
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

    public function activateMaster(Request $request, string $kind, int $id): JsonResponse
    {
        return $this->run(function () use ($request, $kind, $id): array {
            app(MasterData::class)->activate($request->user()->id, $kind, $id);

            return ['status' => 'activated'];
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
