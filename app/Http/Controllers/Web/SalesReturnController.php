<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SalesReturn;
use App\Services\AuditLogger;
use App\Services\SalesmanStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SalesReturnController extends Controller
{
    public function index(Request $request): View
    {
        $status = trim((string) $request->string('status'));

        return view('admin.returns.index', [
            'status' => $status,
            'returns' => SalesReturn::with(['customer', 'salesman.user'])
                ->withCount('items')
                ->when($status !== '', fn ($query) => $query->where('status', $status))
                ->orderByDesc('returned_at')
                ->paginate(30)
                ->withQueryString(),
        ]);
    }

    public function show(SalesReturn $salesReturn): View
    {
        return view('admin.returns.show', [
            'return' => $salesReturn->load([
                'customer',
                'salesman.user',
                'visit',
                'order',
                'items.product',
                'statusChanger',
            ]),
        ]);
    }

    public function updateStatus(
        Request $request,
        SalesReturn $salesReturn,
        SalesmanStockService $stock,
        AuditLogger $audit,
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'status_note' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($salesReturn->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Only pending returns can be reviewed.',
            ]);
        }

        if (
            $validated['status'] === 'rejected'
            && trim((string) ($validated['status_note'] ?? '')) === ''
        ) {
            throw ValidationException::withMessages([
                'status_note' => 'A rejection reason is required.',
            ]);
        }

        if ($validated['status'] === 'approved') {
            $stock->applyApprovedReturn($salesReturn, $request->user());
        }

        $before = ['status' => $salesReturn->status];

        $salesReturn->update([
            'status' => $validated['status'],
            'status_note' => $validated['status_note'] ?? null,
            'status_changed_by' => $request->user()->id,
            'status_changed_at' => now(),
        ]);

        $audit->record('sales_return.status_changed', $salesReturn, $before, [
            'status' => $salesReturn->status,
            'status_note' => $salesReturn->status_note,
        ]);

        return back()->with('status', __('Return status updated.'));
    }
}
