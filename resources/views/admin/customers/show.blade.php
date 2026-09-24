<x-layouts.app>
@php $limit = $customer->credit_limit === null ? null : (float) $customer->credit_limit; $outstanding = (float) $creditSnapshot['outstanding_balance']; $utilization = $limit !== null && $limit > 0 ? min(999, round(($outstanding / $limit) * 100)) : null; @endphp
<div class="mb-6 flex items-start justify-between gap-4"><div><h1 class="text-2xl font-bold">{{ $customer->name }}</h1><p class="mt-1 text-sm text-slate-400">{{ $customer->code }} · {{ $customer->territory?->name ?? __('No territory') }}</p></div><div class="flex flex-wrap gap-2"><a href="{{ route('admin.customers.statement', $customer) }}" class="rounded-xl bg-white/10 px-4 py-2.5 font-semibold">{{ __('Statement') }}</a>@if(auth()->user()->hasPermission('customers:manage'))<a href="{{ route('admin.customers.edit', $customer) }}" class="rounded-xl bg-indigo-500 px-4 py-2.5 font-semibold">{{ __('Edit') }}</a>@endif</div></div>
<div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
<div class="rounded-2xl border border-white/10 bg-slate-900 p-5"><p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Outstanding') }}</p><p class="mt-2 text-2xl font-bold">{{ $creditSnapshot['currency'] }} {{ number_format($outstanding, 2) }}</p></div>
<div class="rounded-2xl border border-white/10 bg-slate-900 p-5"><p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Credit limit') }}</p><p class="mt-2 text-2xl font-bold">{{ $limit === null ? __('No limit') : $customer->credit_currency.' '.number_format($limit, 2) }}</p></div>
<div class="rounded-2xl border border-white/10 bg-slate-900 p-5"><p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Credit terms') }}</p><p class="mt-2 text-2xl font-bold">{{ $customer->credit_terms_days }} {{ __('days') }}</p></div>
<div class="rounded-2xl border border-white/10 bg-slate-900 p-5"><p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Credit utilization') }}</p><p class="mt-2 text-2xl font-bold">{{ $utilization === null ? '—' : $utilization.'%' }}</p></div>
</div>
<div class="grid gap-5 lg:grid-cols-2">
<section class="rounded-2xl border border-white/10 bg-slate-900 p-5"><dl class="grid gap-4 sm:grid-cols-2"><div><dt class="text-sm text-slate-400">{{ __('Contact') }}</dt><dd>{{ $customer->contact_person ?? '—' }}</dd></div><div><dt class="text-sm text-slate-400">{{ __('Phone') }}</dt><dd>{{ $customer->phone ?? '—' }}</dd></div><div><dt class="text-sm text-slate-400">{{ __('Coordinates') }}</dt><dd>{{ $customer->latitude ?? '—' }}, {{ $customer->longitude ?? '—' }}</dd></div><div><dt class="text-sm text-slate-400">{{ __('Geofence') }}</dt><dd>{{ $customer->geofence_radius_meters }} m</dd></div><div><dt class="text-sm text-slate-400">{{ __('Price list') }}</dt><dd>{{ $customer->priceList?->name ?? __('Base prices') }}</dd></div><div class="sm:col-span-2"><dt class="text-sm text-slate-400">{{ __('Address') }}</dt><dd>{{ $customer->address ?? '—' }}</dd></div></dl></section>
<section class="rounded-2xl border border-white/10 bg-slate-900 p-5"><h2 class="font-semibold">{{ __('Receivables aging') }}</h2><div class="mt-4 space-y-4">@forelse($aging as $row)<div class="rounded-xl bg-slate-950 p-4"><div class="mb-3 flex items-center justify-between"><span class="font-semibold">{{ $row['currency'] }}</span><span>{{ __('Total') }} {{ number_format($row['outstanding_total'], 2) }}</span></div><div class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-5"><div><p class="text-slate-500">{{ __('Current') }}</p><p>{{ number_format($row['current'], 2) }}</p></div><div><p class="text-slate-500">1–30</p><p>{{ number_format($row['days_1_30'], 2) }}</p></div><div><p class="text-slate-500">31–60</p><p>{{ number_format($row['days_31_60'], 2) }}</p></div><div><p class="text-slate-500">61–90</p><p>{{ number_format($row['days_61_90'], 2) }}</p></div><div><p class="text-slate-500">90+</p><p class="{{ $row['days_90_plus'] > 0 ? 'text-rose-300' : '' }}">{{ number_format($row['days_90_plus'], 2) }}</p></div></div></div>@empty<p class="text-sm text-slate-400">{{ __('No credit receivables.') }}</p>@endforelse</div></section>
<section class="rounded-2xl border border-white/10 bg-slate-900 p-5"><h2 class="font-semibold">{{ __('Route memberships') }}</h2><div class="mt-4 space-y-2">@forelse($customer->routeMemberships as $membership)<a href="{{ route('admin.routes.show', $membership->route) }}" class="block rounded-xl bg-slate-950 p-3">{{ $membership->route?->name }} · {{ __('stop') }} {{ $membership->sequence_number }}</a>@empty<p class="text-sm text-slate-400">{{ __('Not assigned to a route.') }}</p>@endforelse</div></section>
<section class="rounded-2xl border border-white/10 bg-slate-900 p-5 lg:col-span-2">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="font-semibold">{{ __('Reorder recommendations') }}</h2>
            <p class="mt-1 text-sm text-slate-400">{{ __('Explainable suggestions from approved purchase cadence and recent average quantities. One-off purchases are not treated as recurring demand.') }}</p>
        </div>
        <span class="rounded-full bg-white/5 px-3 py-1 text-xs text-slate-400">{{ count($reorderRecommendations) }} {{ __('due or upcoming') }}</span>
    </div>
    <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        @forelse($reorderRecommendations as $recommendation)
            <article class="rounded-xl border border-white/10 bg-slate-950 p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0"><p class="truncate font-semibold">{{ $recommendation['name'] }}</p><p class="mt-1 text-xs text-slate-500">{{ $recommendation['sku'] }} · {{ $recommendation['purchase_count'] }} {{ __('approved orders') }}</p></div>
                    <span class="shrink-0 rounded-full px-2 py-1 text-[10px] font-semibold uppercase {{ $recommendation['days_until_due'] <= 0 ? 'bg-rose-500/10 text-rose-300' : 'bg-amber-500/10 text-amber-300' }}">{{ $recommendation['days_until_due'] < 0 ? __('Overdue') : ($recommendation['days_until_due'] === 0 ? __('Due today') : __('Due soon')) }}</span>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-2 text-xs"><div class="rounded-lg bg-white/5 p-2"><p class="text-slate-500">{{ __('Suggested') }}</p><p class="mt-1 font-semibold">{{ number_format($recommendation['suggested_quantity'], 2) }} {{ $recommendation['unit'] }}</p></div><div class="rounded-lg bg-white/5 p-2"><p class="text-slate-500">{{ __('Typical cycle') }}</p><p class="mt-1 font-semibold">{{ $recommendation['typical_interval_days'] }} {{ __('days') }}</p></div></div>
                <p class="mt-3 text-xs leading-5 text-slate-400">{{ $recommendation['reason'] }}</p>
                @if($recommendation['stock_enabled'])<p class="mt-2 text-[11px] {{ $recommendation['stock_limited'] ? 'text-amber-300' : 'text-emerald-300' }}">{{ __('Available salesman stock') }}: {{ number_format((float) $recommendation['available_stock'], 2) }} {{ $recommendation['unit'] }}</p>@endif
            </article>
        @empty
            <div class="rounded-xl border border-dashed border-white/10 p-6 text-sm text-slate-500 md:col-span-2 xl:col-span-3">{{ __('No repeat-purchase recommendation is due yet. FieldPulse needs at least two approved purchases of a product before predicting its reorder cycle.') }}</div>
        @endforelse
    </div>
</section>
<section class="rounded-2xl border border-white/10 bg-slate-900 p-5"><div class="flex items-center justify-between gap-3"><h2 class="font-semibold">{{ __('Follow-ups') }}</h2><a href="{{ route('admin.follow-ups.index') }}" class="text-sm text-indigo-300 hover:underline">{{ __('All follow-ups') }}</a></div><div class="mt-4 space-y-3">@forelse($customer->followUps->take(8) as $followUp)<div class="rounded-xl bg-slate-950 p-3"><div class="flex justify-between gap-3"><span class="font-medium">{{ __(str($followUp->type)->title()->toString()) }}</span><span class="text-xs {{ $followUp->status === 'pending' && $followUp->due_at?->isPast() ? 'text-rose-300' : 'text-slate-400' }}">{{ $followUp->due_at?->copy()->setTimezone($timezone)->format('Y-m-d H:i') }}</span></div><div class="mt-1 text-xs text-slate-400">{{ $followUp->assignedSalesman?->full_name ?? __('Unassigned') }} · {{ __(str($followUp->status)->title()->toString()) }}</div>@if($followUp->notes)<p class="mt-2 text-sm">{{ $followUp->notes }}</p>@endif</div>@empty<p class="text-sm text-slate-400">{{ __('No follow-ups scheduled.') }}</p>@endforelse</div>
@if(auth()->user()->hasPermission('customers:manage'))<form method="POST" action="{{ route('admin.customers.follow-ups.store', $customer) }}" class="mt-5 grid gap-3 border-t border-white/10 pt-5 sm:grid-cols-2">@csrf<select name="type" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">@foreach(\App\Models\CustomerFollowUp::TYPES as $type)<option value="{{ $type }}">{{ __(str($type)->title()->toString()) }}</option>@endforeach</select><select name="priority" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">@foreach(\App\Models\CustomerFollowUp::PRIORITIES as $priority)<option value="{{ $priority }}" @selected($priority === 'normal')>{{ __(str($priority)->title()->toString()) }}</option>@endforeach</select><select name="assigned_salesman_id" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5"><option value="">{{ __('Unassigned') }}</option>@foreach($salesmen as $salesman)<option value="{{ $salesman->id }}">{{ $salesman->employee_code }} · {{ $salesman->full_name }}</option>@endforeach</select><input type="datetime-local" name="due_at" value="{{ $defaultFollowUpAt }}" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5" required><textarea name="notes" rows="2" placeholder="{{ __('Follow-up notes') }}" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 sm:col-span-2"></textarea><button class="rounded-xl bg-indigo-500 px-4 py-2.5 font-semibold sm:col-span-2">{{ __('Schedule follow-up') }}</button></form>@endif
</section>
<section class="rounded-2xl border border-white/10 bg-slate-900 p-5 lg:col-span-2"><div class="flex items-center justify-between gap-3"><h2 class="font-semibold">{{ __('Recent call activity') }}</h2><a href="{{ route('admin.call-activities.index') }}" class="text-sm text-indigo-300 hover:underline">{{ __('All calls') }}</a></div><div class="mt-4 overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="text-slate-400"><tr><th class="pb-2 pr-4">{{ __('Called') }}</th><th class="pb-2 pr-4">{{ __('Salesman') }}</th><th class="pb-2 pr-4">{{ __('Outcome') }}</th><th class="pb-2">{{ __('Notes') }}</th></tr></thead><tbody class="divide-y divide-white/10">@forelse($customer->callActivities->take(20) as $call)<tr><td class="py-3 pr-4">{{ $call->called_at?->format('Y-m-d H:i') }}</td><td class="py-3 pr-4">{{ $call->user?->name ?? '—' }}</td><td class="py-3 pr-4">{{ $call->outcome ? str($call->outcome)->replace('_', ' ')->title() : __('Not recorded') }}</td><td class="py-3">{{ $call->notes ?? '—' }}</td></tr>@empty<tr><td colspan="4" class="py-5 text-slate-400">{{ __('No call activity recorded.') }}</td></tr>@endforelse</tbody></table></div></section>

<section class="rounded-2xl border border-white/10 bg-slate-900 p-5 lg:col-span-2">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="font-semibold">{{ __('Customer messaging') }}</h2>
            <p class="mt-1 text-sm text-slate-400">{{ __('Queue an audited WhatsApp or SMS message to the customer phone number.') }}</p>
        </div>
        <span class="text-sm text-slate-400">{{ $customer->phone ?: $customer->alternate_phone ?: __('No phone') }}</span>
    </div>

    @if(auth()->user()->hasPermission('customers:manage'))
        <form method="POST" action="{{ route('admin.customers.communications.store', $customer) }}" class="mt-5 grid gap-3 md:grid-cols-[180px_220px_1fr_auto]">
            @csrf
            <select name="channel" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5" required>
                <option value="whatsapp">{{ __('WhatsApp') }}</option>
                <option value="sms">{{ __('SMS') }}</option>
            </select>
            <select name="kind" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5" required>
                <option value="custom">{{ __('Custom message') }}</option>
                <option value="payment_reminder">{{ __('Payment reminder') }}</option>
                <option value="order_update">{{ __('Order update') }}</option>
                <option value="statement">{{ __('Statement message') }}</option>
            </select>
            <textarea name="message" rows="2" maxlength="1600" required placeholder="{{ __('Message to customer') }}" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5"></textarea>
            <button class="rounded-xl bg-emerald-600 px-5 py-2.5 font-semibold">{{ __('Send') }}</button>
        </form>
    @endif

    <div class="mt-5 overflow-x-auto">
        <table class="min-w-full text-left text-sm">
            <thead class="text-slate-400">
            <tr>
                <th class="pb-2 pr-4">{{ __('Created') }}</th>
                <th class="pb-2 pr-4">{{ __('Channel') }}</th>
                <th class="pb-2 pr-4">{{ __('Type') }}</th>
                <th class="pb-2 pr-4">{{ __('Status') }}</th>
                <th class="pb-2 pr-4">{{ __('By') }}</th>
                <th class="pb-2">{{ __('Message') }}</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-white/10">
            @forelse($customer->communicationDeliveries->take(10) as $delivery)
                <tr>
                    <td class="py-3 pr-4 whitespace-nowrap">{{ $delivery->created_at?->copy()->setTimezone($timezone)->format('Y-m-d H:i') }}</td>
                    <td class="py-3 pr-4">{{ __(str($delivery->channel)->upper()->toString()) }}</td>
                    <td class="py-3 pr-4">{{ __(str($delivery->kind)->replace('_', ' ')->title()->toString()) }}</td>
                    <td class="py-3 pr-4">{{ __(str($delivery->status)->title()->toString()) }}@if($delivery->last_error)<div class="mt-1 max-w-xs text-xs text-rose-300">{{ $delivery->last_error }}</div>@endif</td>
                    <td class="py-3 pr-4">{{ $delivery->creator?->name ?? '—' }}</td>
                    <td class="py-3 max-w-xl">{{ str($delivery->message)->limit(180) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-5 text-slate-400">{{ __('No WhatsApp or SMS messages recorded yet.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="rounded-2xl border border-white/10 bg-slate-900 p-5 lg:col-span-2">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="font-semibold">{{ __('Customer portal') }}</h2>
            <p class="mt-1 text-sm text-slate-400">{{ __('Create revocable, expiring read-only links for invoices, verified payments and statements.') }}</p>
        </div>
    </div>

    @if(session('portal_url'))
        <div class="mt-4 rounded-xl border border-emerald-400/20 bg-emerald-500/10 p-4">
            <p class="text-sm font-semibold text-emerald-200">{{ __('New portal link — copy it now') }}</p>
            <input readonly value="{{ session('portal_url') }}" class="mt-2 w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-sm">
        </div>
    @endif

    @if(auth()->user()->hasPermission('customers:manage'))
        <form method="POST" action="{{ route('admin.customers.portal-accesses.store', $customer) }}" class="mt-5 grid gap-3 sm:grid-cols-[1fr_180px_auto]">
            @csrf
            <input name="label" maxlength="120" placeholder="{{ __('Label, e.g. Accounts Department') }}" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">
            <select name="expires_days" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">
                <option value="7">{{ __('7 days') }}</option>
                <option value="30" selected>{{ __('30 days') }}</option>
                <option value="90">{{ __('90 days') }}</option>
                <option value="365">{{ __('1 year') }}</option>
            </select>
            <button class="rounded-xl bg-indigo-500 px-5 py-2.5 font-semibold">{{ __('Create portal link') }}</button>
        </form>
    @endif

    <div class="mt-5 overflow-x-auto">
        <table class="min-w-full text-left text-sm">
            <thead class="text-slate-400"><tr><th class="pb-2 pr-4">{{ __('Label') }}</th><th class="pb-2 pr-4">{{ __('Expires') }}</th><th class="pb-2 pr-4">{{ __('Last used') }}</th><th class="pb-2 pr-4">{{ __('Status') }}</th><th class="pb-2">{{ __('Action') }}</th></tr></thead>
            <tbody class="divide-y divide-white/10">
            @forelse($customer->portalAccesses->take(10) as $access)
                <tr>
                    <td class="py-3 pr-4">{{ $access->label ?: __('Customer access') }}</td>
                    <td class="py-3 pr-4 whitespace-nowrap">{{ $access->expires_at?->copy()->setTimezone($timezone)->format('Y-m-d H:i') }}</td>
                    <td class="py-3 pr-4 whitespace-nowrap">{{ $access->last_used_at?->copy()->setTimezone($timezone)->format('Y-m-d H:i') ?? '—' }}</td>
                    <td class="py-3 pr-4">
                        @if($access->revoked_at)
                            <span class="text-rose-300">{{ __('Revoked') }}</span>
                        @elseif($access->expires_at?->isPast())
                            <span class="text-amber-300">{{ __('Expired') }}</span>
                        @else
                            <span class="text-emerald-300">{{ __('Active') }}</span>
                        @endif
                    </td>
                    <td class="py-3">
                        @if(auth()->user()->hasPermission('customers:manage') && !$access->revoked_at && $access->expires_at?->isFuture())
                            <form method="POST" action="{{ route('admin.customers.portal-accesses.destroy', [$customer, $access]) }}">@csrf @method('DELETE')<button class="text-sm font-semibold text-rose-300 hover:underline">{{ __('Revoke') }}</button></form>
                        @else
                            <span class="text-slate-500">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-5 text-slate-400">{{ __('No customer portal links have been created.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
</div>
</x-layouts.app>