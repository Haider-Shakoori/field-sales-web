<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $status = trim((string) $request->string('status'));
        $paymentType = trim((string) $request->string('payment_type'));

        $orders = Order::with(['customer', 'salesman.user'])
            ->withCount('items')
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when(
                $paymentType !== '',
                fn ($query) => $query->where('payment_type', $paymentType)
            )
            ->orderByDesc('ordered_at')
            ->paginate(30)
            ->withQueryString();

        return view('admin.orders.index', compact(
            'orders',
            'status',
            'paymentType',
        ));
    }

    public function show(Order $order): View
    {
        return view('admin.orders.show', [
            'order' => $order->load([
                'customer',
                'salesman.user',
                'device',
                'visit',
                'priceList',
                'items.product',
                'statusChanger',
            ]),
        ]);
    }

    public function updateStatus(
        Request $request,
        Order $order,
        AuditLogger $audit,
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected', 'cancelled'])],
            'status_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $allowed = match ($order->status) {
            'pending' => ['approved', 'rejected', 'cancelled'],
            'approved' => ['cancelled'],
            default => [],
        };

        if (! in_array($validated['status'], $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => 'This order cannot transition from '.$order->status.' to '.$validated['status'].'.',
            ]);
        }

        if (
            in_array($validated['status'], ['rejected', 'cancelled'], true)
            && trim((string) ($validated['status_note'] ?? '')) === ''
        ) {
            throw ValidationException::withMessages([
                'status_note' => 'A reason is required when rejecting or cancelling an order.',
            ]);
        }

        $before = [
            'status' => $order->status,
            'status_note' => $order->status_note,
        ];

        $order->update([
            'status' => $validated['status'],
            'status_note' => $validated['status_note'] ?? null,
            'status_changed_by' => $request->user()->id,
            'status_changed_at' => now(),
        ]);

        $audit->record('order.status_changed', $order, $before, [
            'status' => $order->status,
            'status_note' => $order->status_note,
        ]);

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('status', 'Order status updated.');
    }
}
