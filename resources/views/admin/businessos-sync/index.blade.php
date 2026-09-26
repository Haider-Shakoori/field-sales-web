<x-layouts.app>
    @php
        $streamLabels = [
            'pull:products' => __('Products from BusinessOS'),
            'pull:customers' => __('Customers from BusinessOS'),
            'pull:prices' => __('Price lists from BusinessOS'),
            'push:customers' => __('FieldPulse customers to BusinessOS'),
            'push:orders' => __('Approved orders to BusinessOS'),
            'push:collections' => __('Verified collections to BusinessOS'),
        ];
        $streamDescriptions = [
            'pull:products' => __('Create or update products using BusinessOS external IDs and SKU reconciliation.'),
            'pull:customers' => __('Create or update customer master data without echoing imported customers back as new records.'),
            'pull:prices' => __('Create or update price lists and product price tiers.'),
            'push:customers' => __('Send locally created or changed FieldPulse customers using idempotent upsert events.'),
            'push:orders' => __('Send approved orders once, including customer, salesman and line-item references.'),
            'push:collections' => __('Send verified collections once, preserving receipt and payment references.'),
        ];
        $enabledStreams = collect([
            $policy['pull_products'] ? 'pull:products' : null,
            $policy['pull_customers'] ? 'pull:customers' : null,
            $policy['pull_prices'] ? 'pull:prices' : null,
            $policy['push_field_customers'] ? 'push:customers' : null,
            $policy['push_orders'] ? 'push:orders' : null,
            $policy['push_collections'] ? 'push:collections' : null,
        ])->filter()->values();
    @endphp

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">{{ __('BusinessOS Sync Center') }}</h1>
            <p class="mt-1 max-w-3xl text-sm text-slate-400">{{ __('FieldPulse stays independent. This page controls and monitors the optional API-based synchronization boundary with BusinessOS.') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('organization.edit') }}" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-2.5 text-sm font-semibold text-slate-300 hover:bg-white/5">{{ __('Integration settings') }}</a>
            @if(auth()->user()->hasPermission('settings:manage'))
                <form method="POST" action="{{ route('admin.businessos-sync.health') }}">
                    @csrf
                    <button class="rounded-xl border border-white/10 bg-slate-900 px-4 py-2.5 text-sm font-semibold text-slate-300 hover:bg-white/5">{{ __('Check connection') }}</button>
                </form>
                <form method="POST" action="{{ route('admin.businessos-sync.run') }}">
                    @csrf
                    <button class="rounded-xl bg-indigo-500 px-4 py-2.5 text-sm font-semibold hover:bg-indigo-400" {{ $policy['enabled'] ? '' : 'disabled' }}>{{ __('Sync now') }}</button>
                </form>
            @endif
        </div>
    </div>

    @if(session('businessos_health'))
        @php($health = session('businessos_health'))
        <div class="mb-5 rounded-2xl border {{ $health['ok'] ? 'border-emerald-400/20 bg-emerald-500/10 text-emerald-200' : 'border-rose-400/20 bg-rose-500/10 text-rose-200' }} p-4 text-sm">
            {{ $health['message'] }}
        </div>
    @endif

    <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Connector') }}</p>
            <p class="mt-2 text-xl font-bold {{ $policy['enabled'] ? 'text-emerald-300' : 'text-amber-300' }}">{{ $policy['enabled'] ? __('Enabled') : __('Disabled') }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ $policy['platform_available'] ? __('Server credentials configured') : __('Server credentials not configured') }}</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Organization mapping') }}</p>
            <p class="mt-2 truncate text-lg font-bold">{{ $policy['organization_key'] ?: __('Not set') }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ __('No API token is stored in tenant settings.') }}</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Pending outbound') }}</p>
            <p class="mt-2 text-2xl font-bold">{{ number_format($outbox['pending']) }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ __('Waiting for BusinessOS acknowledgement') }}</p>
        </div>
        <div class="rounded-2xl border {{ $outbox['failed'] > 0 ? 'border-rose-400/20' : 'border-white/10' }} bg-slate-900 p-5">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Failed outbound') }}</p>
            <p class="mt-2 text-2xl font-bold {{ $outbox['failed'] > 0 ? 'text-rose-300' : '' }}">{{ number_format($outbox['failed']) }}</p>
            @if($outbox['failed'] > 0 && auth()->user()->hasPermission('settings:manage'))
                <form method="POST" action="{{ route('admin.businessos-sync.retry-failed') }}" class="mt-2">
                    @csrf
                    <button class="text-xs font-semibold text-rose-300 hover:text-rose-200">{{ __('Retry failed events') }}</button>
                </form>
            @endif
        </div>
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Automatic interval') }}</p>
            <p class="mt-2 text-2xl font-bold">{{ $policy['sync_interval_minutes'] }} min</p>
            <p class="mt-1 text-xs text-slate-500">{{ __('Scheduler evaluates due tenants every 5 minutes.') }}</p>
        </div>
    </div>

    @if(! $policy['enabled'])
        <div class="mb-5 rounded-2xl border border-amber-400/20 bg-amber-500/10 p-5">
            <h2 class="font-semibold text-amber-200">{{ __('Synchronization is currently inactive') }}</h2>
            <p class="mt-2 text-sm leading-6 text-amber-200/80">{{ __('Enable BusinessOS synchronization in Organization Settings and configure the platform connector URL/token on the server. FieldPulse continues to work normally while integration is off.') }}</p>
        </div>
    @endif

    <section class="mb-5 overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
        <div class="border-b border-white/10 px-5 py-4">
            <h2 class="font-semibold">{{ __('Synchronization streams') }}</h2>
            <p class="mt-1 text-xs text-slate-500">{{ __('Each stream can be enabled or disabled in Organization Settings. Manual single-stream syncs use the same policy and audit trail as scheduled runs.') }}</p>
        </div>
        <div class="divide-y divide-white/10">
            @foreach($streams as $stream)
                @php
                    $state = $states->get($stream);
                    $enabled = $enabledStreams->contains($stream);
                @endphp
                <div class="grid gap-4 px-5 py-4 lg:grid-cols-[minmax(0,1fr)_150px_190px_100px] lg:items-center">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-semibold">{{ $streamLabels[$stream] ?? $stream }}</p>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $enabled ? 'bg-emerald-500/10 text-emerald-300' : 'bg-slate-700/50 text-slate-400' }}">{{ $enabled ? __('Allowed') : __('Disabled') }}</span>
                        </div>
                        <p class="mt-1 text-xs leading-5 text-slate-500">{{ $streamDescriptions[$stream] ?? '' }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-wide text-slate-500">{{ __('Status') }}</p>
                        <p class="mt-1 text-sm font-semibold">{{ $state?->status ? __(str($state->status)->replace('_', ' ')->title()->toString()) : __('Never synced') }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-wide text-slate-500">{{ __('Last success') }}</p>
                        <p class="mt-1 text-sm">{{ $state?->last_success_at ? $state->last_success_at->setTimezone(auth()->user()->tenant->timezone)->format('Y-m-d H:i') : '—' }}</p>
                        @if($state?->last_error)
                            <p class="mt-1 line-clamp-2 text-xs text-rose-300">{{ $state->last_error }}</p>
                        @endif
                    </div>
                    <div class="lg:text-end">
                        @if(auth()->user()->hasPermission('settings:manage'))
                            <form method="POST" action="{{ route('admin.businessos-sync.run') }}">
                                @csrf
                                <input type="hidden" name="streams[]" value="{{ $stream }}">
                                <button class="rounded-lg bg-white/10 px-3 py-2 text-xs font-semibold hover:bg-white/20 disabled:cursor-not-allowed disabled:opacity-40" {{ $policy['enabled'] && $enabled ? '' : 'disabled' }}>{{ __('Sync') }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
        <div class="border-b border-white/10 px-5 py-4">
            <h2 class="font-semibold">{{ __('Recent sync runs') }}</h2>
            <p class="mt-1 text-xs text-slate-500">{{ __('Every manual, retry and scheduled run is recorded for troubleshooting and auditability.') }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-[900px] w-full text-left text-sm">
                <thead class="bg-white/5 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">{{ __('Started') }}</th>
                        <th class="px-5 py-3">{{ __('Trigger') }}</th>
                        <th class="px-5 py-3">{{ __('Status') }}</th>
                        <th class="px-5 py-3">{{ __('Requested by') }}</th>
                        <th class="px-5 py-3">{{ __('Streams') }}</th>
                        <th class="px-5 py-3">{{ __('Result') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                    @forelse($runs as $run)
                        <tr>
                            <td class="px-5 py-4">{{ ($run->started_at ?? $run->created_at)->setTimezone(auth()->user()->tenant->timezone)->format('Y-m-d H:i:s') }}</td>
                            <td class="px-5 py-4">{{ __(str($run->trigger)->title()->toString()) }}</td>
                            <td class="px-5 py-4">
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ in_array($run->status, ['succeeded'], true) ? 'bg-emerald-500/10 text-emerald-300' : (in_array($run->status, ['failed'], true) ? 'bg-rose-500/10 text-rose-300' : (in_array($run->status, ['partial'], true) ? 'bg-amber-500/10 text-amber-300' : 'bg-white/5 text-slate-300')) }}">{{ __(str($run->status)->replace('_', ' ')->title()->toString()) }}</span>
                            </td>
                            <td class="px-5 py-4">{{ $run->requestedBy?->name ?: __('Scheduler') }}</td>
                            <td class="px-5 py-4"><span class="text-xs text-slate-400">{{ collect($run->requested_streams ?? [])->implode(', ') ?: '—' }}</span></td>
                            <td class="max-w-sm px-5 py-4">
                                @if($run->error_message)
                                    <p class="line-clamp-2 text-xs text-rose-300">{{ $run->error_message }}</p>
                                @else
                                    <p class="text-xs text-slate-500">{{ $run->finished_at ? __('Completed').' '.$run->finished_at->setTimezone(auth()->user()->tenant->timezone)->format('H:i:s') : __('Waiting or running') }}</p>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-8 text-center text-slate-500">{{ __('No BusinessOS sync has run for this organization yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.app>
