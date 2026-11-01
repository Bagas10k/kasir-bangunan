<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Services\V2\Operations;
use App\Services\V2\MasterData;
use App\Services\V2\SupplyTrips;
use App\Services\V2\ReportsDocuments;
use App\Services\V2\OpeningBalances;
use App\Services\V2\DeliveryTrips;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use RuntimeException;

class WorkspaceController extends Controller
{
    public function __construct(private Operations $operations) {}

    public function index(Request $request)
    {
        $role = $this->operations->role($request->user()->id);
        $shifts = DB::table('v2_shifts')->when($role !== 'admin', fn ($q) => $q->where('user_id', $request->user()->id))->orderByDesc('id')->limit(30)->get();
        $invoices = DB::table('v2_invoices')->orderByDesc('id')->limit(100)->get();
        $payments = DB::table('v2_payments')->when($role !== 'admin', fn ($q) => $q->where('requested_by', $request->user()->id))->orderByDesc('id')->limit(50)->get();
        $products = DB::table('products')->where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name', 'category', 'selling_price', 'stock_quantity', 'unit']);
        $customers = DB::table('v2_parties')->where('kind', 'customer')->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $sales = DB::table('v2_sales')->when($role !== 'admin', fn ($q) => $q->where('user_id', $request->user()->id))->orderByDesc('id')->limit(30)->get();
        $deliveries = DB::table('v2_deliveries')->whereIn('sale_id', $sales->pluck('id'))->orderByDesc('id')->get();
        $suppliers = $role === 'admin' ? DB::table('v2_parties')->where('kind', 'supplier')->where('is_active', true)->get() : collect();
        $dispatchVehicles = DB::table('vehicles')->where('status', 'active')->get();
        $vehicles = $role === 'admin' ? $dispatchVehicles : collect();
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
        $date = $request->query('date', now()->toDateString());
        abort_unless(is_string($date), 422);
        $daily = app(ReportsDocuments::class)->daily($request->user()->id, $date);
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
            abort_unless(is_string($startDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) && checkdate((int)substr($startDate, 5, 2), (int)substr($startDate, 8, 2), (int)substr($startDate, 0, 4)), 422);
        } else {
            $startDate = null;
        }

        $endDate = $request->query('end_date');
        if ($endDate !== null && $endDate !== '') {
            abort_unless(is_string($endDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate) && checkdate((int)substr($endDate, 5, 2), (int)substr($endDate, 8, 2), (int)substr($endDate, 0, 4)), 422);
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
            $historyQuery->where('v2_sales.number', 'like', '%' . $saleSearch . '%');
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

        return view('v2.workspace', compact('role', 'shifts', 'invoices', 'payments', 'products', 'customers', 'sales', 'deliveries', 'suppliers', 'vehicles', 'daily', 'driverAdvances', 'dispatchVehicles', 'masterKind', 'masterRecords', 'allMasterRecords', 'historySales', 'historyFilters'));
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
        return $this->run(function () use ($request, $id, $data): array { $this->operations->resolveShift($request->user()->id, $id, $data['reason']); return ['status'=>'closed']; });
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reason'=>['required','string','min:5','max:1000']]);
        return $this->run(function () use ($request,$id,$data): array { $this->operations->rejectPayment($request->user()->id,$id,$data['reason']); return ['status'=>'rejected']; });
    }

    public function template(Request $request, string $kind)
    {
        $bytes = app(MasterData::class)->template($request->user()->id, $kind);
        return response($bytes)->header('Content-Type','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')->header('Content-Disposition','attachment; filename="master-'.$kind.'.xlsx"')->header('Cache-Control','private, no-store');
    }

    public function importPreview(Request $request, string $kind): JsonResponse
    {
        $this->operations->requireRole($request->user()->id, ['admin']);
        $request->validate(['file'=>['required','file','max:2048']]);
        return $this->run(fn () => app(MasterData::class)->preview($request->user()->id,$kind,$request->file('file')->getRealPath()));
    }

    public function importCommit(Request $request, int $id): JsonResponse
    {
        return $this->run(fn () => app(MasterData::class)->commit($request->user()->id,$id));
    }

    public function receive(Request $request): JsonResponse
    {
        $this->operations->requireRole($request->user()->id,['admin']);
        return $this->run(fn () => app(SupplyTrips::class)->receive($request->user()->id,$request->all()));
    }

    public function trip(Request $request): JsonResponse
    {
        $this->operations->requireRole($request->user()->id,['admin']);
        return $this->run(fn () => app(SupplyTrips::class)->trip($request->user()->id,$request->all()));
    }

    public function updateMaster(Request $request, string $kind, int $id): JsonResponse
    {
        return $this->run(function () use ($request,$kind,$id): array { app(MasterData::class)->update($request->user()->id,$kind,$id,$request->all()); return ['status'=>'updated']; });
    }

    public function archiveMaster(Request $request, string $kind, int $id): JsonResponse
    {
        return $this->run(function () use ($request,$kind,$id): array { app(MasterData::class)->archive($request->user()->id,$kind,$id); return ['status'=>'archived']; });
    }

    public function openingPreview(Request $request): JsonResponse
    {
        return $this->run(fn () => app(OpeningBalances::class)->preview($request->user()->id,$request->all()));
    }

    public function document(Request $request, int $id, string $kind)
    {
        $document = app(ReportsDocuments::class)->document($request->user()->id, $id, $kind);
        $invoice = DB::table('v2_invoices')->where('sale_id', $id)->first();
        $document['paid'] = (string) \App\Services\V2\Money::value((string) ($invoice->paid ?? '0'));
        $document['remaining'] = (string) \App\Services\V2\Money::value($document['sale']['total'])->minus($document['paid']);
        $paymentHistory = DB::table('v2_payments')->join('v2_invoices', 'v2_invoices.id', '=', 'v2_payments.invoice_id')->where('v2_invoices.sale_id', $id)->get(['v2_payments.*']);
        return response()->view('v2.document', compact('document', 'paymentHistory'))->header('Cache-Control', 'private, no-store');
    }

    public function opening(Request $request): JsonResponse
    {
        $request->validate(['idempotency_key'=>['required','string','min:8','max:100'],'inventory'=>['sometimes','array','max:1000'],'receivables'=>['sometimes','array','max:1000'],'payables'=>['sometimes','array','max:1000']]);
        return $this->run(fn () => app(OpeningBalances::class)->post($request->user()->id, $request->all()));
    }

    public function dispatch(Request $request, int $id): JsonResponse
    {
        return $this->run(function () use ($request,$id): array { app(DeliveryTrips::class)->dispatch($request->user()->id,$id,$request->all()); return ['status'=>'sent']; });
    }

    public function receiveDelivery(Request $request, int $id): JsonResponse
    {
        return $this->run(function () use ($request,$id): array { app(DeliveryTrips::class)->receive($request->user()->id,$id); return ['status'=>'received']; });
    }

    public function advance(Request $request): JsonResponse
    {
        return $this->run(fn () => app(DeliveryTrips::class)->advance($request->user()->id,$request->all()));
    }

    public function settle(Request $request, int $id): JsonResponse
    {
        return $this->run(fn () => app(DeliveryTrips::class)->settle($request->user()->id,$id,$request->all()));
    }

    private function run(callable $action): JsonResponse
    {
        try { return response()->json($action()); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) { throw $e; }
        catch (InvalidArgumentException|RuntimeException $e) { return response()->json(['message' => $e->getMessage()], 422); }
    }
}
