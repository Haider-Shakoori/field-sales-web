<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\AuditLogger;
use App\Services\CustomerBalanceService;
use App\Services\NotificationService;
use App\Services\SalesmanStockService;
use App\Services\StockSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        return view('admin.orders.index', compact('orders', 'status', 'paymentType'));
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
        NotificationService $notifications,
        CustomerBalanceService $balances,
        SalesmanStockService $stock,
        StockSettingsService $stockSettings,
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

        if (in_array($validated['status'], $allowed, true) === false) {
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

        $order->loadMissing('customer');
        $dueDate = $order->due_date;

        if ($validated['status'] === 'approved' && $order->payment_type === 'credit') {
            $customer = $order->customer;

            if ($customer) {
                if (
                    strtoupper((string) $customer->credit_currency) === strtoupper($order->currency)
                    && $customer->credit_limit !== null
                ) {
                    $projected = round(
                        $balances->outstanding($customer, $order->currency)
                            + (float) $order->grand_total,
                        4,
                    );

                    if (
                        $projected > (float) $customer->credit_limit
                        && trim((string) ($validated['status_note'] ?? '')) === ''
                    ) {
                        throw ValidationException::withMessages([
                            'status_note' => sprintf(
                                'Credit limit exceeded. Projected balance is %s %.2f against a limit of %.2f. Add an override reason to approve.',
                                $order->currency,
                                $projected,
                                (float) $customer->credit_limit,
                            ),
                        ]);
                    }
                }

                $timezone = $request->user()->loadMissing('tenant')->tenant?->timezone
                    ?: config('app.timezone', 'UTC');

                $dueDate = $order->ordered_at
                    ->copy()
                    ->setTimezone($timezone)
                    ->addDays((int) $customer->credit_terms_days)
                    ->toDateString();
            }
        }

        $before = [
            'status' => $order->status,
            'status_note' => $order->status_note,
            'due_date' => $order->due_date?->toDateString(),
        ];

        $previousStatus = $order->status;

        $stockEnabled = $stockSettings->enabled(
            $request->user()->loadMissing('tenant')->tenant,
        );

        DB::transaction(function () use (
            $validated,
            $order,
            $request,
            $stock,
            $stockEnabled,
            $previousStatus,
            $dueDate,
        ): void {
            if ($stockEnabled && $validated['status'] === 'approved') {
                $stock->applyApprovedOrder($order, $request->user());
            }

            if (
                $stockEnabled
                && $previousStatus === 'approved'
                && $validated['status'] === 'cancelled'
            ) {
                $stock->restoreCancelledOrder($order, $request->user());
            }

            $order->update([
                'status' => $validated['status'],
                'due_date' => $dueDate,
                'status_note' => $validated['status_note'] ?? null,
                'status_changed_by' => $request->user()->id,
                'status_changed_at' => now(),
            ]);
        });

        $audit->record('order.status_changed', $order, $before, [
            'status' => $order->status,
            'status_note' => $order->status_note,
            'due_date' => $order->due_date?->toDateString(),
        ]);

        $order->loadMissing('salesman.user');

        if ($order->salesman?->user) {
            $notifications->notify(
                $order->salesman->user,
                'order.status_changed',
                'order_updates',
                'Order '.$order->order_number.' '.str($order->status)->title(),
                'Your order '.$order->order_number.' is now '.$order->status.'.',
                [
                    'order_id' => $order->uuid,
                    'status' => $order->status,
                ],
                in_array($order->status, ['rejected', 'cancelled'], true) ? 'high' : 'normal',
            );
        }

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('status', 'Order status updated.');
    }
}
