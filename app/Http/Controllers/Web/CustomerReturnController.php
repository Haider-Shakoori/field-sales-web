<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CustomerReturn;
use App\Models\Salesman;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use App\Services\VanStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CustomerReturnController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(CustomerReturn::STATUSES)],
            'salesman' => ['nullable', 'uuid'],
        ]);

        $salesman = null;

        if (! empty($validated['salesman'])) {
            $salesman = Salesman::where('uuid', $validated['salesman'])
                ->firstOrFail();
        }

        $returns = CustomerReturn::with(['customer', 'salesman.user', 'order'])
            ->withCount('items')
            ->when(
                $validated['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->when(
                $salesman,
                fn ($query) => $query->where('salesman_id', $salesman->id)
            )
            ->orderByDesc('returned_at')
            ->paginate(30)
            ->withQueryString();

        return view('admin.returns.index', [
            'returns' => $returns,
            'status' => $validated['status'] ?? '',
            'selectedSalesman' => $salesman,
            'salesmen' => Salesman::active()
                ->with('user')
                ->orderBy('employee_code')
                ->get(),
        ]);
    }

    public function show(CustomerReturn $customerReturn): View
    {
        return view('admin.returns.show', [
            'customerReturn' => $customerReturn->load([
                'customer',
                'salesman.user',
                'device',
                'visit',
                'order',
                'items.product',
                'statusChanger',
            ]),
        ]);
    }

    public function updateStatus(
        Request $request,
        CustomerReturn $customerReturn,
        VanStockService $stock,
        AuditLogger $audit,
        NotificationService $notifications,
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'status_note' => ['nullable', 'string', 'max:5000'],
        ]);

        if (
            $validated['status'] === 'rejected'
            && trim((string) ($validated['status_note'] ?? '')) === ''
        ) {
            throw ValidationException::withMessages([
                'status_note' => 'A reason is required when rejecting a return.',
            ]);
        }

        $before = [
            'status' => $customerReturn->status,
            'status_note' => $customerReturn->status_note,
        ];

        DB::transaction(function () use (
            $customerReturn,
            $validated,
            $request,
            $stock,
        ): void {
            $lockedReturn = CustomerReturn::whereKey($customerReturn->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedReturn->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'Only pending returns can be reviewed.',
                ]);
            }

            if ($validated['status'] === 'approved') {
                $stock->approveReturn($lockedReturn, $request->user());
            }

            $lockedReturn->update([
                'status' => $validated['status'],
                'status_note' => $validated['status_note'] ?? null,
                'status_changed_by' => $request->user()->id,
                'status_changed_at' => now(),
            ]);
        });

        $customerReturn->refresh();

        $audit->record('customer_return.status_changed', $customerReturn, $before, [
            'status' => $customerReturn->status,
            'status_note' => $customerReturn->status_note,
        ]);

        $customerReturn->loadMissing('salesman.user');

        if ($customerReturn->salesman?->user) {
            $notifications->notify(
                $customerReturn->salesman->user,
                'customer_return.status_changed',
                'order_updates',
                'Return '.$customerReturn->return_number.' '.str($customerReturn->status)->title(),
                'Your customer return '.$customerReturn->return_number.' is now '.$customerReturn->status.'.',
                [
                    'return_id' => $customerReturn->uuid,
                    'status' => $customerReturn->status,
                ],
                $customerReturn->status === 'rejected' ? 'high' : 'normal',
            );
        }

        return redirect()
            ->route('admin.returns.show', $customerReturn)
            ->with('status', __('Return status updated.'));
    }
}
