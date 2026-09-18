<x-layouts.app>
    <div class="mb-6 flex items-start justify-between gap-4"><div><h1 class="text-2xl font-bold">{{ $territory->name }}</h1><p class="mt-1 text-sm text-slate-400">{{ $territory->code }} · {{ $territory->branch?->name ?? 'Company-wide' }}</p></div>@if(auth()->user()->hasPermission('customers:manage'))<a href="{{ route('admin.territories.edit', $territory) }}" class="rounded-xl bg-indigo-500 px-4 py-2.5 font-semibold">Edit</a>@endif</div>
    <div class="grid gap-5 lg:grid-cols-2">
        <section class="rounded-2xl border border-white/10 bg-slate-900 p-5"><h2 class="font-semibold">Customers</h2><div class="mt-4 space-y-2">@forelse($territory->customers as $customer)<a href="{{ route('admin.customers.show', $customer) }}" class="block rounded-xl bg-slate-950 p-3">{{ $customer->code }} — {{ $customer->name }}</a>@empty<p class="text-sm text-slate-400">No customers.</p>@endforelse</div></section>
        <section class="rounded-2xl border border-white/10 bg-slate-900 p-5"><h2 class="font-semibold">Routes</h2><div class="mt-4 space-y-2">@forelse($territory->routes as $route)<a href="{{ route('admin.routes.show', $route) }}" class="block rounded-xl bg-slate-950 p-3">{{ $route->code }} — {{ $route->name }}</a>@empty<p class="text-sm text-slate-400">No routes.</p>@endforelse</div></section>
    </div>
</x-layouts.app>
