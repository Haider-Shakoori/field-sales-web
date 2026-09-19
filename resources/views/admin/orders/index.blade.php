<x-layouts.app>
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Orders</h1>
        <p class="mt-1 text-sm text-slate-400">Field orders with server-authoritative pricing and approval status.</p>
    </div>

    <form class="mb-5 flex flex-wrap gap-3">
        <select name="status" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-3">
            <option value="">All statuses</option>
            @foreach(\App\Models\Order::STATUSES as $value)
                <option value="{{ $value }}" @selected($status === $value)>{{ str($value)->title() }}</option>
            @endforeach
        </select>
        <select name="payment_type" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-3">
            <option value="">All payment types</option>
            @foreach(\App\Models\Order::PAYMENT_TYPES as $value)
                <option value="{{ $value }}" @selected($paymentType === $value)>{{ str($value)->title() }}</option>
            @endforeach
        </select>
        <button class="rounded-xl bg-indigo-500 px-4 py-3 font-semibold">Filter</button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-white/5">
                    <tr>
                        <th class="px-5 py-3">Order</th>
                        <th class="px-5 py-3">Customer</th>
                        <th class="px-5 py-3">Salesman</th>
                        <th class="px-5 py-3">Date</th>
                        <th class="px-5 py-3">Payment</th>
                        <th class="px-5 py-3">Total</th>
                        <th class="px-5 py-3">Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                @forelse($orders as $order)
                    <tr>
                        <td class="px-5 py-4">{{ $order->order_number }}</td>
                        <td class="px-5 py-4">{{ $order->customer?->name ?? 'Deleted customer' }}</td>
                        <td class="px-5 py-4">{{ $order->salesman?->full_name ?? $order->salesman?->user?->name ?? '—' }}</td>
                        <td class="px-5 py-4">{{ $order->ordered_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-5 py-4">{{ str($order->payment_type)->title() }}</td>
                        <td class="px-5 py-4">{{ number_format((float) $order->grand_total, 2) }} {{ $order->currency }}</td>
                        <td class="px-5 py-4">
                            {{ str($order->status)->title() }}
                            @if($order->pricing_adjusted)
                                <span class="ml-1 rounded bg-amber-500/20 px-2 py-1 text-xs text-amber-300">repriced</span>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-right"><a href="{{ route('admin.orders.show', $order) }}" class="rounded-lg bg-white/10 px-3 py-2">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-5 py-10 text-center text-slate-400">No orders found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-5">{{ $orders->links() }}</div>
</x-layouts.app>
