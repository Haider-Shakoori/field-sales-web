@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <x-ui.page-header title="Dashboard"
                      description="Welcome back! Here's what's happening with your field sales.">
        <x-slot:actions>
            <span class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-500 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                <x-ui.icon name="clock" class="h-4 w-4" />
                {{ now()->format('D, d M Y') }}
            </span>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- KPIs --}}
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat-card label="Total Salesmen"
                        :value="$salesmenTotal ?? '—'"
                        :description="$salesmenTotal === null
                            ? 'Available within a company workspace.'
                            : ($salesmenTotal > 0 ? $salesmenActive.' active' : 'No salesmen yet.')"
                        icon="salesmen"
                        tone="blue" />

        <x-ui.stat-card label="Customer Visits"
                        value="—"
                        description="No visit activity yet."
                        icon="pin"
                        tone="amber" />

        <x-ui.stat-card label="Orders"
                        value="—"
                        description="No orders recorded."
                        icon="clipboard"
                        tone="green" />

        <x-ui.stat-card label="Collections"
                        value="—"
                        description="No collections recorded."
                        icon="currency"
                        tone="gray" />
    </div>

    {{-- Activity and live locations --}}
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-ui.card title="Sales Activity" class="xl:col-span-2">
            <x-ui.empty-state :bordered="false"
                              icon="chart"
                              title="No sales activity yet"
                              description="Daily sales, order and collection trends will appear here once your team starts recording."
                              class="min-h-[15rem]" />
        </x-ui.card>

        <x-ui.card title="Salesmen Live Locations" class="xl:col-span-1">
            <x-ui.empty-state :bordered="false"
                              icon="map"
                              title="No live locations yet"
                              description="Salesman locations will appear here while field tracking is active."
                              class="min-h-[15rem]" />
        </x-ui.card>
    </div>

    {{-- Recent activity --}}
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <x-ui.card title="Recent Visits">
            <x-ui.empty-state :bordered="false"
                              icon="pin"
                              title="No visits yet"
                              description="Visit activity will appear here once salesmen begin checking in."
                              class="min-h-[11rem]" />
        </x-ui.card>

        <x-ui.card title="Recent Orders">
            <x-ui.empty-state :bordered="false"
                              icon="clipboard"
                              title="No orders yet"
                              description="Orders captured by your salesmen will appear here."
                              class="min-h-[11rem]" />
        </x-ui.card>
    </div>

    {{-- Context --}}
    <x-ui.card>
        @if ($tenant)
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400">
                        <x-ui.icon name="building" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-gray-900 dark:text-gray-50">{{ $tenant->name }}</p>
                        <p class="truncate text-xs text-gray-400 dark:text-gray-500">{{ $tenant->timezone }} · {{ $tenant->default_currency }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <x-ui.status-badge :status="$tenant->subscription_status" :label="ucfirst($tenant->subscription_status)" />
                    @if ($user->hasPermission('settings:view'))
                        <x-ui.button href="{{ route('settings.company.edit') }}" variant="secondary" size="sm">Company settings</x-ui.button>
                    @endif
                </div>
            </div>
        @else
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400">
                        <x-ui.icon name="platform" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-50">Platform mode</p>
                        <p class="truncate text-xs text-gray-400 dark:text-gray-500">No company context is attached to this account.</p>
                    </div>
                </div>

                @if ($user->hasPermission('tenants:manage'))
                    <x-ui.button href="{{ route('platform.index') }}" variant="secondary" size="sm" icon="platform">Platform dashboard</x-ui.button>
                @endif
            </div>
        @endif
    </x-ui.card>
@endsection
