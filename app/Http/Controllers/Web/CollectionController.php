<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Services\AuditLogger;
use App\Services\CustomerBalanceService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CollectionController extends Controller
{
    public function index(Request $request): View
    {
        $status = trim((string) $request->string('status'));
        $paymentMethod = trim((string) $request->string('payment_method'));

        $collections = Collection::with(['customer', 'salesman.user'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when(
                $paymentMethod !== '',
                fn ($query) => $query->where('payment_method', $paymentMethod)
            )
            ->orderByDesc('collected_at')
            ->paginate(30)
            ->withQueryString();

        return view('admin.collections.index', compact(
            'collections',
            'status',
            'paymentMethod',
        ));
    }

    public function show(
        Collection $collection,
        CustomerBalanceService $balances,
    ): View {
        $collection->load([
            'customer',
            'salesman.user',
            'device',
            'visit',
            'statusChanger',
        ]);

        return view('admin.collections.show', [
            'collection' => $collection,
            'balances' => $collection->customer
                ? $balances->forCustomer($collection->customer)
                : [],
        ]);
    }

    public function receipt(Collection $collection): View
    {
        return view('admin.collections.receipt', [
            'collection' => $collection->load(['customer', 'salesman.user']),
        ]);
    }

    public function updateStatus(
        Request $request,
        Collection $collection,
        CustomerBalanceService $balances,
        AuditLogger $audit,
        NotificationService $notifications,
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['verified', 'rejected', 'cancelled'])],
            'status_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $allowed = match ($collection->status) {
            'pending' => ['verified', 'rejected', 'cancelled'],
            default => [],
        };

        if (! in_array($validated['status'], $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => 'This collection cannot transition from '
                    .$collection->status.' to '.$validated['status'].'.',
            ]);
        }

        if (
            in_array($validated['status'], ['rejected', 'cancelled'], true)
            && trim((string) ($validated['status_note'] ?? '')) === ''
        ) {
            throw ValidationException::withMessages([
                'status_note' => 'A reason is required when rejecting or cancelling a collection.',
            ]);
        }

        if ($validated['status'] === 'verified') {
            $collection->loadMissing('customer');
            $outstanding = $balances->outstanding(
                $collection->customer,
                $collection->currency,
            );

            if ((float) $collection->amount > $outstanding + 0.0001) {
                throw ValidationException::withMessages([
                    'status' => 'The collection exceeds the current outstanding balance and cannot be verified.',
                ]);
            }
        }

        $before = [
            'status' => $collection->status,
            'status_note' => $collection->status_note,
        ];

        $collection->update([
            'status' => $validated['status'],
            'status_note' => $validated['status_note'] ?? null,
            'status_changed_by' => $request->user()->id,
            'status_changed_at' => now(),
        ]);

        $audit->record('collection.status_changed', $collection, $before, [
            'status' => $collection->status,
            'status_note' => $collection->status_note,
        ]);

        $collection->loadMissing('salesman.user');
        if ($collection->salesman?->user) {
            $notifications->notify(
                $collection->salesman->user,
                'collection.status_changed',
                'collection_updates',
                'Collection '.$collection->receipt_number.' '.str($collection->status)->title(),
                'Your collection '.$collection->receipt_number.' is now '.$collection->status.'.',
                [
                    'collection_id' => $collection->uuid,
                    'status' => $collection->status,
                ],
                $collection->status === 'rejected' ? 'high' : 'normal',
            );
        }

        return redirect()
            ->route('admin.collections.show', $collection)
            ->with('status', 'Collection status updated.');
    }
}
