<?php

namespace App\Services\V2;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Penyedia data tabel operasional V2 dengan pagination, pencarian, dan penyaringan di server.
 * Setiap kueri hanya memuat baris pada halaman aktif beserta relasinya (tanpa potong limit 30/50).
 */
class WorkspaceTables
{
    public const TABLES = [
        'payments',
        'deliveries',
        'shifts',
        'receipts',
        'receipt-drafts',
        'stock-opnames',
        'sale-returns',
        'purchase-returns',
    ];

    public const ADMIN_ONLY = [
        'receipts',
        'receipt-drafts',
        'stock-opnames',
        'sale-returns',
        'purchase-returns',
    ];

    public function __construct(private Operations $operations, private BankAccounts $bankAccounts) {}

    /**
     * @param  array{q?:string|null, filter?:string|null, per_page?:int|string|null, page?:int|string|null}  $params
     * @return array{view:string, data:array<string, mixed>, paginator:LengthAwarePaginator, filters:array{q:string, filter:string, per_page:int, page:int}}
     */
    public function build(int $actorId, string $table, array $params = []): array
    {
        abort_unless(in_array($table, self::TABLES, true), 404);
        $role = $this->operations->role($actorId);
        if (in_array($table, self::ADMIN_ONLY, true) && $role !== 'admin') {
            abort(403);
        }

        $q = trim((string) ($params['q'] ?? ''));
        $perPage = (int) ($params['per_page'] ?? 10);
        if (! in_array($perPage, [10, 25, 50], true)) {
            $perPage = 10;
        }
        $page = max(1, (int) ($params['page'] ?? 1));
        $filter = trim((string) ($params['filter'] ?? ''));

        return match ($table) {
            'payments' => $this->payments($actorId, $role, $q, $filter !== '' ? $filter : 'status:pending,proposed', $perPage, $page),
            'deliveries' => $this->deliveries($actorId, $role, $q, $filter !== '' ? $filter : 'all', $perPage, $page),
            'shifts' => $this->shifts($actorId, $role, $q, $filter !== '' ? $filter : 'all', $perPage, $page),
            'receipts' => $this->receipts($q, $filter !== '' ? $filter : 'all', $perPage, $page),
            'receipt-drafts' => $this->receiptDrafts($q, $filter !== '' ? $filter : 'all', $perPage, $page),
            'stock-opnames' => $this->stockOpnames($q, $filter !== '' ? $filter : 'all', $perPage, $page),
            'sale-returns' => $this->saleReturns($q, $filter !== '' ? $filter : 'all', $perPage, $page),
            'purchase-returns' => $this->purchaseReturns($q, $filter !== '' ? $filter : 'all', $perPage, $page),
        };
    }

    /** Seluruh pembayaran pending/proposed untuk formulir penolakan admin (tidak terpotong limit). */
    public function pendingRejectPayments(int $actorId): Collection
    {
        if ($this->operations->role($actorId) !== 'admin') {
            return collect();
        }

        return DB::table('v2_payments as pm')
            ->leftJoin('v2_invoices as i', 'i.id', '=', 'pm.invoice_id')
            ->leftJoin('v2_parties as p', 'p.id', '=', 'i.party_id')
            ->leftJoin('users as u', 'u.id', '=', 'pm.requested_by')
            ->whereIn('pm.status', ['pending', 'proposed'])
            ->orderByDesc('pm.id')
            ->get(['pm.*', 'i.number as invoice_number', 'i.direction as invoice_direction', 'p.name as party_name', 'u.name as requester_name']);
    }

    /**
     * @return array{view:string, data:array<string, mixed>, paginator:LengthAwarePaginator, filters:array{q:string, filter:string, per_page:int, page:int}}
     */
    private function payments(int $actorId, string $role, string $q, string $filter, int $perPage, int $page): array
    {
        $allowedFilters = ['status:pending,proposed', 'status:posted', 'status:rejected', 'all'];
        if (! in_array($filter, $allowedFilters, true)) {
            $filter = 'status:pending,proposed';
        }

        $query = DB::table('v2_payments as pm')
            ->leftJoin('v2_invoices as i', 'i.id', '=', 'pm.invoice_id')
            ->leftJoin('v2_parties as p', 'p.id', '=', 'i.party_id')
            ->leftJoin('users as u', 'u.id', '=', 'pm.requested_by')
            ->leftJoin('v2_payment_proofs as proof', 'proof.payment_id', '=', 'pm.id')
            ->when($role !== 'admin', fn ($query) => $query->where('pm.requested_by', $actorId))
            ->when($filter === 'status:pending,proposed', fn ($query) => $query->whereIn('pm.status', ['pending', 'proposed']))
            ->when($filter === 'status:posted', fn ($query) => $query->where('pm.status', 'posted'))
            ->when($filter === 'status:rejected', fn ($query) => $query->where('pm.status', 'rejected'))
            ->when($q !== '', fn ($query) => $query->where(function ($search) use ($q): void {
                $like = '%'.$q.'%';
                $search->where('i.number', 'like', $like)
                    ->orWhere('p.name', 'like', $like)
                    ->orWhere('u.name', 'like', $like)
                    ->orWhere('pm.reference', 'like', $like);
            }))
            ->select(['pm.*', 'i.number as invoice_number', 'i.direction as invoice_direction', 'p.name as party_name', 'u.name as requester_name', 'proof.id as proof_id'])
            ->orderByDesc('pm.id');

        $paginator = $this->paginate($query, 'payments', $q, $filter, $perPage, $page);

        return [
            'view' => 'v2.tables.payments-results',
            'paginator' => $paginator,
            'filters' => ['q' => $q, 'filter' => $filter, 'per_page' => $perPage, 'page' => $paginator->currentPage()],
            'data' => [
                'payments' => $paginator,
                'isAdmin' => $role === 'admin',
                'bankAccounts' => $this->bankAccounts->active(),
                'bankAccountNames' => $this->bankAccounts->nameMap(false),
            ],
        ];
    }

    /**
     * @return array{view:string, data:array<string, mixed>, paginator:LengthAwarePaginator, filters:array{q:string, filter:string, per_page:int, page:int}}
     */
    private function deliveries(int $actorId, string $role, string $q, string $filter, int $perPage, int $page): array
    {
        $allowedFilters = ['all', 'status:prepared', 'status:sent', 'status:received'];
        if (! in_array($filter, $allowedFilters, true)) {
            $filter = 'all';
        }

        $query = DB::table('v2_deliveries as d')
            ->join('v2_sales as s', 's.id', '=', 'd.sale_id')
            ->leftJoin('vehicles as v', 'v.id', '=', 'd.vehicle_id')
            ->when($role !== 'admin', fn ($query) => $query->where('s.user_id', $actorId))
            ->when($filter === 'status:prepared', fn ($query) => $query->where('d.status', 'prepared'))
            ->when($filter === 'status:sent', fn ($query) => $query->where('d.status', 'sent'))
            ->when($filter === 'status:received', fn ($query) => $query->where('d.status', 'received'))
            ->when($q !== '', fn ($query) => $query->where(function ($search) use ($q): void {
                $like = '%'.$q.'%';
                $search->where('d.number', 'like', $like)
                    ->orWhere('d.destination', 'like', $like)
                    ->orWhere('d.driver_name', 'like', $like)
                    ->orWhere('v.license_plate', 'like', $like)
                    ->orWhere('s.number', 'like', $like);
            }))
            ->select(['d.*', 'v.license_plate'])
            ->orderByRaw("CASE WHEN d.status IN ('prepared', 'sent') THEN 0 ELSE 1 END")
            ->orderByDesc('d.id');

        $paginator = $this->paginate($query, 'deliveries', $q, $filter, $perPage, $page);
        $dispatchVehicles = DB::table('vehicles')->where('status', 'active')->get();
        $drivers = DB::table('v2_drivers as drivers')
            ->leftJoin('v2_parties as employees', 'employees.id', '=', 'drivers.employee_id')
            ->where('drivers.is_active', true)
            ->where(fn ($query) => $query->where('drivers.kind', 'external')->orWhere('employees.is_active', true))
            ->orderBy('drivers.name')
            ->get(['drivers.*', 'employees.name as employee_name']);

        return [
            'view' => 'v2.tables.deliveries-results',
            'paginator' => $paginator,
            'filters' => ['q' => $q, 'filter' => $filter, 'per_page' => $perPage, 'page' => $paginator->currentPage()],
            'data' => [
                'deliveries' => $paginator,
                'dispatchVehicles' => $dispatchVehicles,
                'drivers' => $drivers,
            ],
        ];
    }

    /**
     * @return array{view:string, data:array<string, mixed>, paginator:LengthAwarePaginator, filters:array{q:string, filter:string, per_page:int, page:int}}
     */
    private function shifts(int $actorId, string $role, string $q, string $filter, int $perPage, int $page): array
    {
        $allowedFilters = ['all', 'status:open', 'status:review', 'status:closed'];
        if (! in_array($filter, $allowedFilters, true)) {
            $filter = 'all';
        }

        $query = DB::table('v2_shifts as s')
            ->leftJoin('users as u', 'u.id', '=', 's.user_id')
            ->when($role !== 'admin', fn ($query) => $query->where(fn ($visible) => $visible->where('s.is_shared', true)->orWhere('s.user_id', $actorId)))
            ->when($filter === 'status:open', fn ($query) => $query->where('s.status', 'open'))
            ->when($filter === 'status:review', fn ($query) => $query->where('s.status', 'review'))
            ->when($filter === 'status:closed', fn ($query) => $query->where('s.status', 'closed'))
            ->when($q !== '', fn ($query) => $query->where(function ($search) use ($q): void {
                $like = '%'.$q.'%';
                $search->where('u.name', 'like', $like)
                    ->orWhere('s.opened_at', 'like', $like)
                    ->orWhere('s.business_date', 'like', $like);
            }))
            ->select(['s.*', 'u.name as user_name'])
            ->orderByDesc('s.id');

        $paginator = $this->paginate($query, 'shifts', $q, $filter, $perPage, $page);

        return [
            'view' => 'v2.tables.shifts-results',
            'paginator' => $paginator,
            'filters' => ['q' => $q, 'filter' => $filter, 'per_page' => $perPage, 'page' => $paginator->currentPage()],
            'data' => [
                'shifts' => $paginator,
                'isAdmin' => $role === 'admin',
            ],
        ];
    }

    /**
     * @return array{view:string, data:array<string, mixed>, paginator:LengthAwarePaginator, filters:array{q:string, filter:string, per_page:int, page:int}}
     */
    private function receipts(string $q, string $filter, int $perPage, int $page): array
    {
        $allowedFilters = ['all', 'type:purchase,restock', 'type:opening', 'pay:payable', 'pay:cash,bank'];
        if (! in_array($filter, $allowedFilters, true)) {
            $filter = 'all';
        }

        $bankCodes = $this->bankAccounts->active()->pluck('account_code')->map(fn ($c): string => (string) $c)->all();
        $cashBankCodes = array_values(array_unique(array_merge(['1101', '1102'], $bankCodes)));

        $query = DB::table('inventory_receipts')
            ->leftJoin('users as void_user', 'void_user.id', '=', 'inventory_receipts.voided_by')
            ->when($filter === 'type:purchase,restock', fn ($query) => $query->whereIn('inventory_receipts.receipt_type', ['purchase', 'restock']))
            ->when($filter === 'type:opening', fn ($query) => $query->where('inventory_receipts.receipt_type', 'opening'))
            ->when($filter === 'pay:payable', fn ($query) => $query->where('inventory_receipts.credit_account', '2101'))
            ->when($filter === 'pay:cash,bank', fn ($query) => $query->whereIn('inventory_receipts.credit_account', $cashBankCodes))
            ->when($q !== '', fn ($query) => $query->where(function ($search) use ($q): void {
                $like = '%'.$q.'%';
                $search->where('inventory_receipts.receipt_number', 'like', $like)
                    ->orWhere('inventory_receipts.supplier_name', 'like', $like)
                    ->orWhere('inventory_receipts.supplier_invoice_number', 'like', $like)
                    ->orWhere('inventory_receipts.notes', 'like', $like)
                    ->orWhereExists(function ($sub) use ($like): void {
                        $sub->selectRaw('1')
                            ->from('inventory_receipt_items as iri')
                            ->join('products as p', 'p.id', '=', 'iri.product_id')
                            ->whereColumn('iri.inventory_receipt_id', 'inventory_receipts.id')
                            ->where('p.name', 'like', $like);
                    });
            }))
            ->select(['inventory_receipts.*', 'void_user.name as voided_by_name'])
            ->orderByDesc('inventory_receipts.receipt_date')
            ->orderByDesc('inventory_receipts.id');

        $paginator = $this->paginate($query, 'receipts', $q, $filter, $perPage, $page);
        $ids = collect($paginator->items())->pluck('id');

        $receiptItems = $ids->isEmpty() ? collect() : DB::table('inventory_receipt_items')
            ->join('products', 'products.id', '=', 'inventory_receipt_items.product_id')
            ->whereIn('inventory_receipt_items.inventory_receipt_id', $ids)
            ->orderBy('inventory_receipt_items.id')
            ->get(['inventory_receipt_items.inventory_receipt_id', 'inventory_receipt_items.quantity', 'inventory_receipt_items.unit_cost', 'inventory_receipt_items.subtotal', 'products.name as product_name', 'products.unit as product_unit'])
            ->groupBy('inventory_receipt_id');

        $receiptFinalAudit = $ids->isEmpty() || ! Schema::hasTable('v2_receipt_drafts')
            ? collect()
            : DB::table('v2_receipt_drafts as d')
                ->leftJoin('users as u', 'u.id', '=', 'd.confirmed_by')
                ->whereIn('d.receipt_id', $ids)
                ->where('d.status', 'confirmed')
                ->get(['d.receipt_id', 'd.confirmed_at', 'u.name as confirmed_by_name'])
                ->keyBy('receipt_id');

        return [
            'view' => 'v2.tables.receipts-results',
            'paginator' => $paginator,
            'filters' => ['q' => $q, 'filter' => $filter, 'per_page' => $perPage, 'page' => $paginator->currentPage()],
            'data' => [
                'receiptHistory' => $paginator,
                'receiptItems' => $receiptItems,
                'receiptFinalAudit' => $receiptFinalAudit,
            ],
        ];
    }

    /**
     * @return array{view:string, data:array<string, mixed>, paginator:LengthAwarePaginator, filters:array{q:string, filter:string, per_page:int, page:int}}
     */
    private function receiptDrafts(string $q, string $filter, int $perPage, int $page): array
    {
        $allowedFilters = ['all', 'status:draft', 'status:cancelled'];
        if (! in_array($filter, $allowedFilters, true)) {
            $filter = 'all';
        }

        if (! Schema::hasTable('v2_receipt_drafts')) {
            $empty = $this->emptyPaginator('receipt-drafts', $q, $filter, $perPage, $page);

            return [
                'view' => 'v2.tables.receipt-drafts-results',
                'paginator' => $empty,
                'filters' => ['q' => $q, 'filter' => $filter, 'per_page' => $perPage, 'page' => 1],
                'data' => ['receiptDrafts' => $empty, 'draftProducts' => collect()],
            ];
        }

        $query = DB::table('v2_receipt_drafts as d')
            ->join('v2_parties as p', 'p.id', '=', 'd.supplier_id')
            ->leftJoin('users as u', 'u.id', '=', 'd.created_by')
            ->when($filter === 'all', fn ($query) => $query->whereIn('d.status', ['draft', 'cancelled']))
            ->when($filter === 'status:draft', fn ($query) => $query->where('d.status', 'draft'))
            ->when($filter === 'status:cancelled', fn ($query) => $query->where('d.status', 'cancelled'))
            ->when($q !== '', fn ($query) => $query->where(function ($search) use ($q): void {
                $like = '%'.$q.'%';
                $search->where('p.name', 'like', $like)
                    ->orWhere('d.supplier_invoice_number', 'like', $like)
                    ->orWhere('d.notes', 'like', $like)
                    ->orWhere('u.name', 'like', $like);
                if (preg_match('/^(?:DRF-)?0*(\d+)$/i', $q, $m)) {
                    $search->orWhere('d.id', (int) $m[1]);
                }
            }))
            ->select(['d.*', 'p.name as supplier_name', 'u.name as author_name'])
            ->orderByDesc('d.id');

        $paginator = $this->paginate($query, 'receipt-drafts', $q, $filter, $perPage, $page);
        foreach ($paginator->items() as $draft) {
            $draft->items = json_decode((string) $draft->items, true, 512, JSON_THROW_ON_ERROR);
        }
        $productIds = collect($paginator->items())->flatMap(fn ($draft) => array_column($draft->items, 'product_id'))->unique();
        $draftProducts = $productIds->isEmpty() ? collect() : DB::table('products')
            ->whereIn('id', $productIds)
            ->get(['id', 'name', 'sku', 'unit', 'cost_price', 'stock_quantity'])
            ->keyBy('id');

        return [
            'view' => 'v2.tables.receipt-drafts-results',
            'paginator' => $paginator,
            'filters' => ['q' => $q, 'filter' => $filter, 'per_page' => $perPage, 'page' => $paginator->currentPage()],
            'data' => [
                'receiptDrafts' => $paginator,
                'draftProducts' => $draftProducts,
            ],
        ];
    }

    /**
     * @return array{view:string, data:array<string, mixed>, paginator:LengthAwarePaginator, filters:array{q:string, filter:string, per_page:int, page:int}}
     */
    private function stockOpnames(string $q, string $filter, int $perPage, int $page): array
    {
        $allowedFilters = ['all', 'variance:yes', 'variance:no'];
        if (! in_array($filter, $allowedFilters, true)) {
            $filter = 'all';
        }

        if (! Schema::hasTable('v2_stock_opnames')) {
            $empty = $this->emptyPaginator('stock-opnames', $q, $filter, $perPage, $page);

            return [
                'view' => 'v2.tables.stock-opnames-results',
                'paginator' => $empty,
                'filters' => ['q' => $q, 'filter' => $filter, 'per_page' => $perPage, 'page' => 1],
                'data' => ['stockOpnames' => $empty, 'stockOpnameItems' => collect()],
            ];
        }

        $query = DB::table('v2_stock_opnames as o')
            ->leftJoin('users as u', 'u.id', '=', 'o.created_by')
            ->when($filter === 'variance:yes', fn ($query) => $query->where(fn ($v) => $v->where('o.gain_total', '>', 0)->orWhere('o.loss_total', '>', 0)))
            ->when($filter === 'variance:no', fn ($query) => $query->where('o.gain_total', '<=', 0)->where('o.loss_total', '<=', 0))
            ->when($q !== '', fn ($query) => $query->where(function ($search) use ($q): void {
                $like = '%'.$q.'%';
                $search->where('o.number', 'like', $like)
                    ->orWhere('o.notes', 'like', $like)
                    ->orWhere('u.name', 'like', $like)
                    ->orWhereExists(function ($sub) use ($like): void {
                        $sub->selectRaw('1')
                            ->from('v2_stock_opname_items as oi')
                            ->join('products as p', 'p.id', '=', 'oi.product_id')
                            ->whereColumn('oi.stock_opname_id', 'o.id')
                            ->where('p.name', 'like', $like);
                    });
            }))
            ->select(['o.*', 'u.name as author_name'])
            ->orderByDesc('o.opname_date')
            ->orderByDesc('o.id');

        $paginator = $this->paginate($query, 'stock-opnames', $q, $filter, $perPage, $page);
        $ids = collect($paginator->items())->pluck('id');
        $stockOpnameItems = $ids->isEmpty() ? collect() : DB::table('v2_stock_opname_items as oi')
            ->join('products as p', 'p.id', '=', 'oi.product_id')
            ->whereIn('oi.stock_opname_id', $ids)
            ->orderBy('oi.id')
            ->get(['oi.*', 'p.name as product_name', 'p.sku as product_sku', 'p.unit as product_unit'])
            ->groupBy('stock_opname_id');

        return [
            'view' => 'v2.tables.stock-opnames-results',
            'paginator' => $paginator,
            'filters' => ['q' => $q, 'filter' => $filter, 'per_page' => $perPage, 'page' => $paginator->currentPage()],
            'data' => [
                'stockOpnames' => $paginator,
                'stockOpnameItems' => $stockOpnameItems,
            ],
        ];
    }

    /**
     * @return array{view:string, data:array<string, mixed>, paginator:LengthAwarePaginator, filters:array{q:string, filter:string, per_page:int, page:int}}
     */
    private function saleReturns(string $q, string $filter, int $perPage, int $page): array
    {
        if (! Schema::hasTable('v2_sale_returns')) {
            $empty = $this->emptyPaginator('sale-returns', $q, 'all', $perPage, $page);

            return [
                'view' => 'v2.tables.sale-returns-results',
                'paginator' => $empty,
                'filters' => ['q' => $q, 'filter' => 'all', 'per_page' => $perPage, 'page' => 1],
                'data' => ['saleReturns' => $empty],
            ];
        }

        $query = DB::table('v2_sale_returns as r')
            ->join('v2_sales as s', 's.id', '=', 'r.sale_id')
            ->leftJoin('v2_parties as c', 'c.id', '=', 's.customer_id')
            ->leftJoin('users as u', 'u.id', '=', 'r.created_by')
            ->when($q !== '', fn ($query) => $query->where(function ($search) use ($q): void {
                $like = '%'.$q.'%';
                $search->where('r.number', 'like', $like)
                    ->orWhere('s.number', 'like', $like)
                    ->orWhere('c.name', 'like', $like)
                    ->orWhere('r.reason', 'like', $like)
                    ->orWhere('u.name', 'like', $like);
            }))
            ->select(['r.*', 's.number as sale_number', 'c.name as customer_name', 'u.name as author_name'])
            ->orderByDesc('r.return_date')
            ->orderByDesc('r.id');

        $paginator = $this->paginate($query, 'sale-returns', $q, 'all', $perPage, $page);

        return [
            'view' => 'v2.tables.sale-returns-results',
            'paginator' => $paginator,
            'filters' => ['q' => $q, 'filter' => 'all', 'per_page' => $perPage, 'page' => $paginator->currentPage()],
            'data' => ['saleReturns' => $paginator],
        ];
    }

    /**
     * @return array{view:string, data:array<string, mixed>, paginator:LengthAwarePaginator, filters:array{q:string, filter:string, per_page:int, page:int}}
     */
    private function purchaseReturns(string $q, string $filter, int $perPage, int $page): array
    {
        if (! Schema::hasTable('v2_purchase_returns')) {
            $empty = $this->emptyPaginator('purchase-returns', $q, 'all', $perPage, $page);

            return [
                'view' => 'v2.tables.purchase-returns-results',
                'paginator' => $empty,
                'filters' => ['q' => $q, 'filter' => 'all', 'per_page' => $perPage, 'page' => 1],
                'data' => ['purchaseReturns' => $empty],
            ];
        }

        $query = DB::table('v2_purchase_returns as r')
            ->join('inventory_receipts as rc', 'rc.id', '=', 'r.receipt_id')
            ->leftJoin('users as u', 'u.id', '=', 'r.created_by')
            ->when($q !== '', fn ($query) => $query->where(function ($search) use ($q): void {
                $like = '%'.$q.'%';
                $search->where('r.number', 'like', $like)
                    ->orWhere('rc.receipt_number', 'like', $like)
                    ->orWhere('rc.supplier_name', 'like', $like)
                    ->orWhere('r.reason', 'like', $like)
                    ->orWhere('u.name', 'like', $like);
            }))
            ->select(['r.*', 'rc.receipt_number', 'rc.supplier_name', 'u.name as author_name'])
            ->orderByDesc('r.return_date')
            ->orderByDesc('r.id');

        $paginator = $this->paginate($query, 'purchase-returns', $q, 'all', $perPage, $page);

        return [
            'view' => 'v2.tables.purchase-returns-results',
            'paginator' => $paginator,
            'filters' => ['q' => $q, 'filter' => 'all', 'per_page' => $perPage, 'page' => $paginator->currentPage()],
            'data' => ['purchaseReturns' => $paginator],
        ];
    }

    private function paginate($query, string $table, string $q, string $filter, int $perPage, int $page): LengthAwarePaginator
    {
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $paginator->setPath(route('v2.tables.show', ['table' => $table]))
            ->appends(['q' => $q, 'filter' => $filter, 'per_page' => $perPage]);

        return $paginator;
    }

    private function emptyPaginator(string $table, string $q, string $filter, int $perPage, int $page): LengthAwarePaginator
    {
        return (new LengthAwarePaginator([], 0, $perPage, $page, [
            'path' => route('v2.tables.show', ['table' => $table]),
        ]))->appends(['q' => $q, 'filter' => $filter, 'per_page' => $perPage]);
    }
}
