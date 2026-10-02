<x-layouts.app title="Customer bonuses">
<div class="space-y-6">
<div><p class="text-sm font-semibold uppercase tracking-wider text-indigo-300">Sales promotions</p><h1 class="mt-1 text-2xl font-bold">Customer bonuses</h1><p class="mt-2 text-sm text-slate-400">Automatically add free reward products when an order qualifies.</p></div>
@if(auth()->user()?->hasPermission('catalog:manage'))
<form method="POST" action="{{ route('admin.promotions.store') }}" class="grid gap-4 rounded-2xl border border-white/10 bg-slate-900 p-5 lg:grid-cols-3">@csrf
<label><span class="text-sm text-slate-300">Promotion name</span><input class="fp-input mt-1 w-full" name="name" required placeholder="Buy 10 get 1 free"></label>
<label><span class="text-sm text-slate-300">Qualifying product</span><select class="fp-input mt-1 w-full" name="qualifying_product_id"><option value="">Any / use order amount</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->sku }} — {{ $product->name }}</option>@endforeach</select></label>
<label><span class="text-sm text-slate-300">Required quantity</span><input class="fp-input mt-1 w-full" type="number" step="0.0001" min="0" name="qualifying_quantity"></label>
<label><span class="text-sm text-slate-300">Minimum order amount</span><input class="fp-input mt-1 w-full" type="number" step="0.01" min="0" name="minimum_order_amount" placeholder="Optional"></label>
<label><span class="text-sm text-slate-300">Reward type</span><select class="fp-input mt-1 w-full" name="reward_type" required><option value="product">Free product</option><option value="cash">Cash award</option></select></label>
<label><span class="text-sm text-slate-300">Bonus product</span><select class="fp-input mt-1 w-full" name="reward_product_id" required><option value="">Select reward</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->sku }} — {{ $product->name }}</option>@endforeach</select></label>
<label><span class="text-sm text-slate-300">Bonus quantity</span><input class="fp-input mt-1 w-full" type="number" step="0.0001" min="0.0001" name="reward_quantity" value="1"></label>
<label><span class="text-sm text-slate-300">Cash award (AFN)</span><input class="fp-input mt-1 w-full" type="number" step="0.01" min="0.01" name="cash_reward_amount" placeholder="For cash reward"></label>
<label><span class="text-sm text-slate-300">Starts</span><input class="fp-input mt-1 w-full" type="datetime-local" name="starts_at"></label>
<label><span class="text-sm text-slate-300">Ends</span><input class="fp-input mt-1 w-full" type="datetime-local" name="ends_at"></label>
<div class="flex items-end"><button class="fp-btn fp-btn-primary w-full">Create promotion</button></div>
</form>
@endif
<div class="overflow-x-auto rounded-2xl border border-white/10 bg-slate-900/60"><table class="fp-table min-w-full"><thead><tr><th>Promotion</th><th>Qualifies when</th><th>Reward</th><th>Period</th><th>Status</th></tr></thead><tbody>
@forelse($promotions as $promotion)<tr><td class="font-semibold">{{ $promotion->name }}</td><td>@if($promotion->qualifyingProduct){{ $promotion->qualifying_quantity }} × {{ $promotion->qualifyingProduct->name }}@endif @if($promotion->minimum_order_amount)<span class="block text-xs text-slate-400">Order ≥ {{ number_format((float)$promotion->minimum_order_amount,2) }}</span>@endif</td><td>@if($promotion->reward_type === 'cash'){{ number_format((float)$promotion->cash_reward_amount,2) }} AFN cash @else{{ $promotion->reward_quantity }} × {{ $promotion->rewardProduct?->name }}@endif</td><td class="text-xs">{{ $promotion->starts_at?->format('d M Y') ?? 'Now' }} → {{ $promotion->ends_at?->format('d M Y') ?? 'Open' }}</td><td>@if(auth()->user()?->hasPermission('catalog:manage'))<form method="POST" action="{{ route('admin.promotions.update',$promotion) }}">@csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $promotion->is_active ? 0 : 1 }}"><button class="fp-btn fp-btn-secondary">{{ $promotion->is_active ? 'Active' : 'Inactive' }}</button></form>@else{{ $promotion->is_active ? 'Active' : 'Inactive' }}@endif</td></tr>
@empty<tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">No customer bonus promotions yet.</td></tr>@endforelse
</tbody></table></div>{{ $promotions->links() }}
</div>
</x-layouts.app>
