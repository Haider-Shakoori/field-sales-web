<x-layouts.app>
    <div class="mb-6 flex items-center justify-between gap-4">
        <div><h1 class="text-2xl font-bold">Territories</h1><p class="mt-1 text-sm text-slate-400">Geographic sales coverage by branch.</p></div>
        @if(auth()->user()->hasPermission('customers:manage'))<a href="{{ route('admin.territories.create') }}" class="rounded-xl bg-indigo-500 px-4 py-2.5 font-semibold">Add territory</a>@endif
    </div>
    <div class="grid gap-4 lg:grid-cols-2">
        @forelse($territories as $territory)
            <a href="{{ route('admin.territories.show', $territory) }}" class="rounded-2xl border border-white/10 bg-slate-900 p-5 hover:bg-slate-800">
                <div class="flex items-center justify-between gap-3"><h2 class="font-semibold">{{ $territory->code }} — {{ $territory->name }}</h2><span class="text-xs text-slate-400">{{ $territory->is_active ? 'Active' : 'Inactive' }}</span></div>
                <p class="mt-2 text-sm text-slate-400">{{ $territory->branch?->name ?? 'Company-wide' }}</p>
                <p class="mt-3 text-sm">{{ $territory->customers_count }} customers · {{ $territory->routes_count }} routes</p>
            </a>
        @empty
            <div class="rounded-2xl border border-white/10 bg-slate-900 p-8 text-slate-400">No territories found.</div>
        @endforelse
    </div>
    <div class="mt-5">{{ $territories->links() }}</div>
</x-layouts.app>
