<x-layouts.app>
@php
    $openStages = ['new', 'contacted', 'qualified', 'proposal', 'negotiation'];
@endphp
<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold tracking-tight">{{ __('Leads & sales pipeline') }}</h1>
        <p class="mt-1 text-sm text-slate-400">{{ __('Capture prospects, assign ownership, move opportunities through the pipeline and convert won leads into customers.') }}</p>
    </div>
    <a href="{{ route('admin.customers.index') }}" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-2.5 text-sm font-semibold text-slate-300 hover:bg-white/5">{{ __('Customers') }}</a>
</div>

<div class="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
    <div class="rounded-2xl border border-white/10 bg-slate-900 p-4"><p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Open leads') }}</p><p class="mt-2 text-2xl font-bold">{{ collect($openStages)->sum(fn($stage) => (int) ($stageCounts[$stage] ?? 0)) }}</p></div>
    <div class="rounded-2xl border border-white/10 bg-slate-900 p-4"><p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Open pipeline value') }}</p><p class="mt-2 text-2xl font-bold">{{ number_format($pipelineValue, 2) }} <span class="text-xs font-medium text-slate-500">{{ __('mixed currencies') }}</span></p></div>
    <div class="rounded-2xl border border-white/10 bg-slate-900 p-4"><p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Won leads') }}</p><p class="mt-2 text-2xl font-bold text-emerald-300">{{ (int) ($stageCounts['won'] ?? 0) }}</p></div>
    <div class="rounded-2xl border border-white/10 bg-slate-900 p-4"><p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Lost leads') }}</p><p class="mt-2 text-2xl font-bold text-rose-300">{{ (int) ($stageCounts['lost'] ?? 0) }}</p></div>
</div>

<form method="GET" class="mb-5 grid gap-3 rounded-2xl border border-white/10 bg-slate-900 p-4 md:grid-cols-[1fr_160px_160px_220px_auto]">
    <input name="search" value="{{ $filters['search'] }}" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5" placeholder="{{ __('Search name, contact, phone or email') }}">
    <select name="stage" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5"><option value="">{{ __('All stages') }}</option>@foreach($stages as $stage)<option value="{{ $stage }}" @selected($filters['stage'] === $stage)>{{ __(str($stage)->replace('_', ' ')->title()->toString()) }}</option>@endforeach</select>
    <select name="priority" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5"><option value="">{{ __('All priorities') }}</option>@foreach($priorities as $priority)<option value="{{ $priority }}" @selected($filters['priority'] === $priority)>{{ __(str($priority)->title()->toString()) }}</option>@endforeach</select>
    <select name="salesman" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5"><option value="">{{ __('All salesmen') }}</option>@foreach($salesmen as $salesman)<option value="{{ $salesman->uuid }}" @selected($filters['salesman'] === $salesman->uuid)>{{ $salesman->employee_code }} · {{ $salesman->full_name }}</option>@endforeach</select>
    <button class="rounded-xl bg-indigo-500 px-4 py-2.5 text-sm font-semibold hover:bg-indigo-400">{{ __('Filter') }}</button>
</form>

<div class="grid gap-5 2xl:grid-cols-[minmax(0,1fr)_360px]">
    <section class="min-w-0">
        <div class="overflow-x-auto pb-3">
            <div class="grid min-w-[1260px] grid-cols-7 gap-3">
                @foreach($stages as $stage)
                    @php
                        $rows = $groupedLeads->get($stage, collect());
                        $tone = match($stage) {
                            'won' => 'border-emerald-400/20',
                            'lost' => 'border-rose-400/20',
                            'negotiation', 'proposal' => 'border-amber-400/20',
                            default => 'border-white/10',
                        };
                    @endphp
                    <div class="min-w-0 rounded-2xl border {{ $tone }} bg-slate-900/80">
                        <div class="flex items-center justify-between border-b border-white/10 px-3 py-3">
                            <div><p class="text-xs font-semibold uppercase tracking-wide text-slate-300">{{ __(str($stage)->replace('_', ' ')->title()->toString()) }}</p><p class="mt-0.5 text-[10px] text-slate-500">{{ (int) ($stageCounts[$stage] ?? 0) }} {{ __('total') }}</p></div>
                            <span class="rounded-full bg-white/5 px-2 py-1 text-xs font-semibold">{{ $rows->count() }}</span>
                        </div>
                        <div class="max-h-[68vh] space-y-2 overflow-y-auto p-2">
                            @forelse($rows as $lead)
                                <a href="{{ route('admin.leads.show', $lead) }}" class="block rounded-xl border border-white/10 bg-slate-950/70 p-3 hover:border-indigo-400/30 hover:bg-slate-950">
                                    <div class="flex items-start justify-between gap-2"><p class="min-w-0 truncate text-sm font-semibold text-slate-100">{{ $lead->name }}</p><span class="shrink-0 rounded-full px-2 py-0.5 text-[9px] uppercase {{ $lead->priority === 'high' ? 'bg-rose-500/10 text-rose-300' : ($lead->priority === 'low' ? 'bg-slate-800 text-slate-500' : 'bg-indigo-500/10 text-indigo-300') }}">{{ __($lead->priority) }}</span></div>
                                    @if($lead->contact_person)<p class="mt-1 truncate text-xs text-slate-500">{{ $lead->contact_person }}</p>@endif
                                    @if($lead->estimated_value !== null)<p class="mt-2 text-sm font-semibold text-emerald-300">{{ $lead->currency }} {{ number_format((float) $lead->estimated_value, 2) }}</p>@endif
                                    <div class="mt-2 flex items-center justify-between gap-2 text-[10px] text-slate-600"><span class="truncate">{{ $lead->assignedSalesman?->employee_code ?? __('Unassigned') }}</span><span>{{ $lead->probability }}%</span></div>
                                </a>
                            @empty
                                <div class="rounded-xl border border-dashed border-white/10 px-3 py-6 text-center text-xs text-slate-600">{{ __('No leads in this stage.') }}</div>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    @if($canManage)
        <aside class="self-start rounded-2xl border border-white/10 bg-slate-900 p-5 2xl:sticky 2xl:top-6">
            <h2 class="text-lg font-semibold">{{ __('Capture lead') }}</h2>
            <p class="mt-1 text-xs leading-5 text-slate-500">{{ __('Create a prospect and assign the next owner while the opportunity is fresh.') }}</p>
            <form method="POST" action="{{ route('admin.leads.store') }}" class="mt-5 space-y-3">@csrf
                <input name="name" value="{{ old('name') }}" required maxlength="180" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5" placeholder="{{ __('Business or prospect name') }}">
                <div class="grid grid-cols-2 gap-3"><input name="contact_person" value="{{ old('contact_person') }}" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5" placeholder="{{ __('Contact person') }}"><input name="phone" value="{{ old('phone') }}" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5" placeholder="{{ __('Phone') }}"></div>
                <input type="email" name="email" value="{{ old('email') }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5" placeholder="{{ __('Email') }}">
                <div class="grid grid-cols-2 gap-3"><select name="source" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">@foreach($sources as $source)<option value="{{ $source }}" @selected(old('source', 'field') === $source)>{{ __(str($source)->replace('_', ' ')->title()->toString()) }}</option>@endforeach</select><select name="priority" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">@foreach($priorities as $priority)<option value="{{ $priority }}" @selected(old('priority', 'normal') === $priority)>{{ __(str($priority)->title()->toString()) }}</option>@endforeach</select></div>
                <select name="assigned_salesman_id" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5"><option value="">{{ __('Unassigned') }}</option>@foreach($salesmen as $salesman)<option value="{{ $salesman->id }}" @selected((string) old('assigned_salesman_id') === (string) $salesman->id)>{{ $salesman->employee_code }} · {{ $salesman->full_name }}</option>@endforeach</select>
                <select name="territory_id" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5"><option value="">{{ __('No territory') }}</option>@foreach($territories as $territory)<option value="{{ $territory->id }}">{{ $territory->code }} · {{ $territory->name }}</option>@endforeach</select>
                <div class="grid grid-cols-[1fr_100px] gap-3"><input type="number" min="0" step="0.01" name="estimated_value" value="{{ old('estimated_value') }}" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5" placeholder="{{ __('Estimated value') }}"><input name="currency" value="{{ old('currency', 'AFN') }}" maxlength="3" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 uppercase"></div>
                <input type="date" name="expected_close_date" value="{{ old('expected_close_date') }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">
                <textarea name="notes" rows="3" maxlength="10000" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5" placeholder="{{ __('Notes') }}">{{ old('notes') }}</textarea>
                <button class="w-full rounded-xl bg-indigo-500 px-4 py-3 font-semibold hover:bg-indigo-400">{{ __('Create lead') }}</button>
            </form>
        </aside>
    @endif
</div>
</x-layouts.app>
