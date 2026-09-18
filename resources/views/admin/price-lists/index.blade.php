<x-layouts.app>
    <div class="mb-6 flex items-center justify-between gap-4"><div><h1 class="text-2xl font-bold">Price lists</h1><p class="mt-1 text-sm text-slate-400">Effective-dated customer pricing with quantity tiers.</p></div>@if(auth()->user()->hasPermission('catalog:manage'))<a href="{{ route('admin.price-lists.create') }}" class="rounded-xl bg-indigo-500 px-4 py-2.5 font-semibold">Add price list</a>@endif</div>
    <div class="grid gap-4 lg:grid-cols-2">
        @forelse($priceLists as $priceList)
            <a href="{{ route('admin.price-lists.show', $priceList) }}" class="rounded-2xl border border-white/10 bg-slate-900 p-5 hover:bg-slate-800"><div class="flex items-center justify-between"><h2 class="font-semibold">{{ $priceList->code }} — {{ $priceList->name }}</h2><span class="text-xs text-slate-400">{{ $priceList->is_active ? 'Active' : 'Inactive' }}</span></div><p class="mt-2 text-sm text-slate-400">{{ $priceList->currency }} · {{ $priceList->effective_from?->toDateString() ?? 'Any start' }} → {{ $priceList->effective_to?->toDateString() ?? 'Open' }}</p><p class="mt-3 text-sm">{{ $priceList->items_count }} tiers · {{ $priceList->customers_count }} customers</p></a>
        @empty<div class="rounded-2xl border border-white/10 bg-slate-900 p-8 text-slate-400">No price lists found.</div>@endforelse
    </div>
    <div class="mt-5">{{ $priceLists->links() }}</div>
</x-layouts.app>
