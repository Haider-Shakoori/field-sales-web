<x-layouts.app>
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">{{ $route->name }}</h1>
            <p class="mt-1 text-sm text-slate-400">{{ $route->code }} · {{ $route->territory?->name ?? 'No territory' }} · {{ implode(', ', $route->weekdays ?? []) ?: 'No weekdays' }}</p>
        </div>
        @if(auth()->user()->hasPermission('customers:manage'))
            <a href="{{ route('admin.routes.edit', $route) }}" class="rounded-xl bg-indigo-500 px-4 py-2.5 font-semibold">Edit</a>
        @endif
    </div>

    <section class="rounded-2xl border border-white/10 bg-slate-900 p-5">
        <h2 class="font-semibold">Planned stops</h2>

        @if(auth()->user()->hasPermission('customers:manage') && $availableCustomers->isNotEmpty())
            <form method="POST" action="{{ route('admin.routes.customers.store', $route) }}" class="mt-4 grid gap-3 md:grid-cols-[1fr_160px_1fr_auto]">
                @csrf
                <select name="customer_id" class="rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
                    <option value="">Add customer…</option>
                    @foreach($availableCustomers as $customer)
                        <option value="{{ $customer->id }}">{{ $customer->code }} — {{ $customer->name }}</option>
                    @endforeach
                </select>
                <input type="number" name="planned_visit_minutes" value="10" min="1" max="480" class="rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
                <input name="notes" placeholder="Notes" class="rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
                <button class="rounded-xl bg-indigo-500 px-4 py-2.5 font-semibold">Add</button>
            </form>
        @endif

        <form method="POST" action="{{ route('admin.routes.customers.reorder', $route) }}" class="mt-5">
            @csrf
            @method('PATCH')
            <div class="space-y-2">
                @forelse($route->customerMemberships as $membership)
                    <div class="grid items-center gap-3 rounded-xl bg-slate-950 p-3 md:grid-cols-[80px_1fr_120px_auto]">
                        <input type="number" min="1" name="positions[{{ $membership->id }}]" value="{{ $membership->sequence_number }}" class="rounded-lg border border-white/10 bg-slate-900 px-3 py-2">
                        <div>
                            <a href="{{ route('admin.customers.show', $membership->customer) }}" class="font-medium">{{ $membership->customer?->code }} — {{ $membership->customer?->name }}</a>
                            <div class="text-xs text-slate-400">{{ $membership->notes }}</div>
                        </div>
                        <div class="text-sm text-slate-400">{{ $membership->planned_visit_minutes }} min</div>
                        @if(auth()->user()->hasPermission('customers:manage'))
                            <button form="remove-route-customer-{{ $membership->id }}" class="rounded-lg bg-red-500/10 px-3 py-2 text-red-300">Remove</button>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-400">No customers on this route.</p>
                @endforelse
            </div>

            @if(auth()->user()->hasPermission('customers:manage') && $route->customerMemberships->isNotEmpty())
                <button class="mt-4 rounded-xl bg-white/10 px-4 py-2.5">Save order</button>
            @endif
        </form>

        @foreach($route->customerMemberships as $membership)
            <form id="remove-route-customer-{{ $membership->id }}" method="POST" action="{{ route('admin.routes.customers.destroy', [$route, $membership]) }}" class="hidden">
                @csrf
                @method('DELETE')
            </form>
        @endforeach
    </section>
</x-layouts.app>
