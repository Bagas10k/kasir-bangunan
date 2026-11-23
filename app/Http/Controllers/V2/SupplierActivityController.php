<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Services\V2\Operations;
use App\Services\V2\SupplierActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SupplierActivityController extends Controller
{
    public function __construct(private Operations $operations, private SupplierActivity $activity) {}

    /** @return array<string, mixed> */
    private function data(Request $request, int $id): array
    {
        $this->operations->requireRole($request->user()->id, ['admin']);
        $request->merge(['from' => $request->query('from', now()->subDays(29)->toDateString()), 'to' => $request->query('to', now()->toDateString())]);
        $input = $request->validate([
            'from' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:today'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'entry_type' => ['nullable', 'string', 'in:invoice,payment', 'required_with:entry_id'],
            'entry_id' => ['nullable', 'integer', 'min:1', 'required_with:entry_type'],
        ]);
        abort_if(Carbon::parse($input['from'])->diffInDays(Carbon::parse($input['to'])) > 365, 422, 'Maksimal 366 hari per rentang.');
        $supplier = DB::table('v2_parties')->where('id', $id)->where('kind', 'supplier')->first();
        abort_unless($supplier, 404);
        $data = $this->activity->build($id, $input['from'], $input['to'], $input['entry_type'] ?? null, isset($input['entry_id']) ? (int) $input['entry_id'] : null);

        return $data + ['supplier' => $supplier, 'from' => $input['from'], 'to' => $input['to'], 'page' => (int) ($input['page'] ?? 1)];
    }

    public function index(Request $request, int $id): JsonResponse
    {
        $data = $this->data($request, $id);
        $total = $data['entries']->count();
        $page = min($data['page'], max(1, (int) ceil($total / 20)));
        $data['activity'] = new LengthAwarePaginator($data['entries']->forPage($page, 20)->values(), $total, 20, $page, [
            'path' => route('v2.suppliers.activity', $id), 'query' => ['from' => $data['from'], 'to' => $data['to']],
        ]);

        return response()->json(['html' => view('v2.supplier-activity-results', $data)->render(), 'total' => $total,
            'last_page' => $data['activity']->lastPage(), 'from' => $data['from'], 'to' => $data['to'],
            'outstanding' => $data['outstanding'], 'awaiting' => $data['awaiting']])->header('Cache-Control', 'private, no-store');
    }

    public function statement(Request $request, int $id): Response
    {
        return response()->view('v2.supplier-statement', $this->data($request, $id))->header('Cache-Control', 'private, no-store');
    }
}
