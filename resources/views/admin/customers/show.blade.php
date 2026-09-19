<x-layouts.app>
    <div class="mb-6 flex items-start justify-between gap-4"><div><h1 class="text-2xl font-bold">{{ $customer->name }}</h1><p class="mt-1 text-sm text-slate-400">{{ $customer->code }} · {{ $customer->territory?->name ?? 'No territory' }}</p></div>@if(auth()->user()->hasPermission('customers:manage'))<a href="{{ route('admin.customers.edit', $customer) }}" class="rounded-xl bg-indigo-500 px-4 py-2.5 font-semibold">Edit</a>@endif</div>
    <div class="grid gap-5 lg:grid-cols-2">
        <section class="rounded-2xl border border-white/10 bg-slate-900 p-5"><dl class="grid gap-4 sm:grid-cols-2"><div><dt class="text-sm text-slate-400">Contact</dt><dd>{{ $customer->contact_person ?? '—' }}</dd></div><div><dt class="text-sm text-slate-400">Phone</dt><dd>{{ $customer->phone ?? '—' }}</dd></div><div><dt class="text-sm text-slate-400">Coordinates</dt><dd>{{ $customer->latitude ?? '—' }}, {{ $customer->longitude ?? '—' }}</dd></div><div><dt class="text-sm text-slate-400">Geofence</dt><dd>{{ $customer->geofence_radius_meters }} m</dd></div><div><dt class="text-sm text-slate-400">Price list</dt><dd>{{ $customer->priceList?->name ?? 'Base prices' }}</dd></div><div class="sm:col-span-2"><dt class="text-sm text-slate-400">Address</dt><dd>{{ $customer->address ?? '—' }}</dd></div></dl></section>
        <section class="rounded-2xl border border-white/10 bg-slate-900 p-5"><h2 class="font-semibold">Route memberships</h2><div class="mt-4 space-y-2">@forelse($customer->routeMemberships as $membership)<a href="{{ route('admin.routes.show', $membership->route) }}" class="block rounded-xl bg-slate-950 p-3">{{ $membership->route?->name }} · stop {{ $membership->sequence_number }}</a>@empty<p class="text-sm text-slate-400">Not assigned to a route.</p>@endforelse</div></section>
        <section class="rounded-2xl border border-white/10 bg-slate-900 p-5 lg:col-span-2">
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-semibold">Recent call activity</h2>
                <a href="{{ route('admin.call-activities.index') }}" class="text-sm text-indigo-300 hover:underline">All calls</a>
            </div>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="text-slate-400"><tr><th class="pb-2 pr-4">Called</th><th class="pb-2 pr-4">Salesman</th><th class="pb-2 pr-4">Outcome</th><th class="pb-2">Notes</th></tr></thead>
                    <tbody class="divide-y divide-white/10">
                    @forelse($customer->callActivities->take(20) as $call)
                        <tr>
                            <td class="py-3 pr-4">{{ $call->called_at?->format('Y-m-d H:i') }}</td>
                            <td class="py-3 pr-4">{{ $call->user?->name ?? '—' }}</td>
                            <td class="py-3 pr-4">{{ $call->outcome ? str($call->outcome)->replace('_', ' ')->title() : 'Not recorded' }}</td>
                            <td class="py-3">{{ $call->notes ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-5 text-slate-400">No call activity recorded.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.app>
