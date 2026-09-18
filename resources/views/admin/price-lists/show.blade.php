<x-layouts.app>
    <div class="mb-6 flex items-start justify-between gap-4"><div><h1 class="text-2xl font-bold">{{ $priceList->name }}</h1><p class="mt-1 text-sm text-slate-400">{{ $priceList->code }} · {{ $priceList->currency }} · {{ $priceList->effective_from?->toDateString() ?? 'Any start' }} → {{ $priceList->effective_to?->toDateString() ?? 'Open' }}</p></div>@if(auth()->user()->hasPermission('catalog:manage'))<a href="{{ route('admin.price-lists.edit', $priceList) }}" class="rounded-xl bg-indigo-500 px-4 py-2.5 font-semibold">Edit</a>@endif</div>
    <section class="rounded-2xl border border-white/10 bg-slate-900 p-5">
        <h2 class="font-semibold">Price tiers</h2>
        @if(auth()->user()->hasPermission('catalog:manage'))
            <form method="POST" action="{{ route('admin.price-lists.items.store', $priceList) }}" class="mt-4 grid gap-3 md:grid-cols-[1fr_160px_180px_auto]">@csrf<select name="product_id" class="rounded-xl border border-white/10 bg-slate-950 px-4 py-3"><option value="">Product…</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->sku }} — {{ $product->name }}</option>@endforeach</select><input type="number" step="0.0001" min="0.0001" name="min_quantity" value="1" class="rounded-xl border border-white/10 bg-slate-950 px-4 py-3"><input type="number" step="0.0001" min="0" name="price" placeholder="Price" class="rounded-xl border border-white/10 bg-slate-950 px-4 py-3"><button class="rounded-xl bg-indigo-500 px-4 py-2.5 font-semibold">Add</button></form>
        @endif
        <div class="mt-5 space-y-3">
            @forelse($priceList->items as $item)
                <div class="rounded-xl bg-slate-950 p-4">
                    <form method="POST" action="{{ route('admin.price-lists.items.update', [$priceList, $item]) }}" class="grid items-end gap-3 md:grid-cols-[1fr_160px_180px_auto]">@csrf @method('PUT')
                        <label><span class="text-xs text-slate-400">Product</span><select name="product_id" class="mt-1 w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2">@foreach($products as $product)<option value="{{ $product->id }}" @selected($product->id === $item->product_id)>{{ $product->sku }} — {{ $product->name }}</option>@endforeach</select></label>
                        <label><span class="text-xs text-slate-400">Min quantity</span><input type="number" step="0.0001" min="0.0001" name="min_quantity" value="{{ $item->min_quantity }}" class="mt-1 w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2"></label>
                        <label><span class="text-xs text-slate-400">Price</span><input type="number" step="0.0001" min="0" name="price" value="{{ $item->price }}" class="mt-1 w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2"></label>
                        @if(auth()->user()->hasPermission('catalog:manage'))<button class="rounded-lg bg-white/10 px-3 py-2">Save</button>@endif
                    </form>
                    @if(auth()->user()->hasPermission('catalog:manage'))<form method="POST" action="{{ route('admin.price-lists.items.destroy', [$priceList, $item]) }}" class="mt-2 text-right">@csrf @method('DELETE')<button class="text-sm text-red-300">Remove tier</button></form>@endif
                </div>
            @empty<p class="text-sm text-slate-400">No price tiers yet.</p>@endforelse
        </div>
    </section>
</x-layouts.app>
