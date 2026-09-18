<x-layouts.app>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div><h1 class="text-2xl font-bold">Products</h1><p class="mt-1 text-sm text-slate-400">Tenant catalog used by mobile ordering and price lists.</p></div>
        @if(auth()->user()->hasPermission('catalog:manage'))<a href="{{ route('admin.products.create') }}" class="rounded-xl bg-indigo-500 px-4 py-2.5 font-semibold">Add product</a>@endif
    </div>
    <form class="mb-5"><input name="search" value="{{ $search }}" placeholder="Search SKU, barcode or name…" class="w-full max-w-xl rounded-xl border border-white/10 bg-slate-900 px-4 py-3"></form>
    <div class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-white/5"><tr><th class="px-5 py-3">Product</th><th class="px-5 py-3">Unit</th><th class="px-5 py-3">Base price</th><th class="px-5 py-3">Status</th><th></th></tr></thead>
                <tbody class="divide-y divide-white/10">
                @forelse($products as $product)
                    <tr><td class="px-5 py-4"><div class="font-medium">{{ $product->sku }} — {{ $product->name }}</div><div class="text-xs text-slate-400">{{ $product->barcode ?? 'No barcode' }}</div></td><td class="px-5 py-4">{{ $product->unit }}</td><td class="px-5 py-4">{{ number_format((float) $product->base_price, 2) }} {{ $product->currency }}</td><td class="px-5 py-4">{{ $product->is_active ? 'Active' : 'Inactive' }}</td><td class="px-5 py-4 text-right"><a href="{{ route('admin.products.show', $product) }}" class="rounded-lg bg-white/10 px-3 py-2">View</a></td></tr>
                @empty<tr><td colspan="5" class="px-5 py-10 text-center text-slate-400">No products found.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-5">{{ $products->links() }}</div>
</x-layouts.app>
