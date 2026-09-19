<x-layouts.app>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">{{ $order->order_number }}</h1>
            <p class="mt-1 text-sm text-slate-400">{{ $order->customer?->name }} · {{ str($order->status)->title() }}</p>
        </div>
        <a href="{{ route('admin.orders.index') }}" class="rounded-xl bg-white/10 px-4 py-2.5">Back to orders</a>
    </div>

    <div class="grid gap-5 lg:grid-cols-3">
        <section class="rounded-2xl border border-white/10 bg-slate-900 p-5 lg:col-span-2">
            <h2 class="font-semibold">Order items</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="text-slate-400"><tr><th class="pb-2 pr-4">Product</th><th class="pb-2 pr-4">Qty</th><th class="pb-2 pr-4">Price</th><th class="pb-2 pr-4">Discount</th><th class="pb-2 text-right">Total</th></tr></thead>
                    <tbody class="divide-y divide-white/10">
                    @foreach($order->items as $item)
                        <tr>
                            <td class="py-3 pr-4">{{ $item->product_name }} <span class="text-xs text-slate-500">{{ $item->product_sku }}</span></td>
                            <td class="py-3 pr-4">{{ (float) $item->quantity }} {{ $item->unit }}</td>
                            <td class="py-3 pr-4">{{ number_format((float) $item->unit_price, 2) }}</td>
                            <td class="py-3 pr-4">{{ (float) $item->discount_percent }}%</td>
                            <td class="py-3 text-right">{{ number_format((float) $item->line_total, 2) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-5 ml-auto max-w-sm space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-slate-400">Subtotal</span><span>{{ number_format((float) $order->subtotal, 2) }} {{ $order->currency }}</span></div>
                <div class="flex justify-between"><span class="text-slate-400">Discount</span><span>{{ number_format((float) $order->discount_total, 2) }} {{ $order->currency }}</span></div>
                <div class="flex justify-between border-t border-white/10 pt-2 text-base font-semibold"><span>Total</span><span>{{ number_format((float) $order->grand_total, 2) }} {{ $order->currency }}</span></div>
                @if($order->pricing_adjusted)
                    <div class="rounded-xl bg-amber-500/10 p-3 text-amber-200">Offline estimate: {{ number_format((float) $order->client_estimated_total, 2) }} {{ $order->currency }}. Server pricing was applied on sync.</div>
                @endif
            </div>
        </section>

        <section class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <h2 class="font-semibold">Details</h2>
            <dl class="mt-4 space-y-4">
                <div><dt class="text-sm text-slate-400">Salesman</dt><dd>{{ $order->salesman?->full_name ?? '—' }}</dd></div>
                <div><dt class="text-sm text-slate-400">Ordered</dt><dd>{{ $order->ordered_at?->format('Y-m-d H:i:s') }}</dd></div>
                <div><dt class="text-sm text-slate-400">Payment</dt><dd>{{ str($order->payment_type)->title() }}</dd></div>
                <div><dt class="text-sm text-slate-400">Visit</dt><dd>{{ $order->visit?->uuid ?? 'Not linked' }}</dd></div>
                <div><dt class="text-sm text-slate-400">Price list</dt><dd>{{ $order->priceList?->name ?? 'Base pricing' }}</dd></div>
                <div><dt class="text-sm text-slate-400">Notes</dt><dd>{{ $order->notes ?? '—' }}</dd></div>
                <div><dt class="text-sm text-slate-400">Status note</dt><dd>{{ $order->status_note ?? '—' }}</dd></div>
            </dl>
        </section>

        @if(auth()->user()->hasPermission('orders:manage'))
            <section class="rounded-2xl border border-white/10 bg-slate-900 p-5 lg:col-span-3">
                <h2 class="font-semibold">Status action</h2>
                @if(in_array($order->status, ['pending', 'approved'], true))
                    <form method="POST" action="{{ route('admin.orders.status', $order) }}" class="mt-4 grid gap-3 md:grid-cols-[220px_1fr_auto]">
                        @csrf
                        @method('PATCH')
                        <select name="status" class="rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
                            @if($order->status === 'pending')
                                <option value="approved">Approve</option>
                                <option value="rejected">Reject</option>
                            @endif
                            <option value="cancelled">Cancel</option>
                        </select>
                        <input name="status_note" class="rounded-xl border border-white/10 bg-slate-950 px-4 py-3" placeholder="Reason required for reject/cancel">
                        <button class="rounded-xl bg-indigo-500 px-5 py-3 font-semibold">Update status</button>
                    </form>
                @else
                    <p class="mt-3 text-sm text-slate-400">This order is in a terminal status.</p>
                @endif
            </section>
        @endif
    </div>
</x-layouts.app>
