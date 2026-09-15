@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <x-ui.page-header title="Dashboard"
                      :description="'Welcome back, '.$user->name.($tenant ? ' — '.$tenant->name : ' — Platform Console').'.'" />

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.card title="Today's Sales">
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-bold text-gray-900 dark:text-gray-50">
                    {{ ($tenant?->default_currency ?? 'AFN') === 'AFN' ? '؋' : ($tenant?->default_currency ?? '') }} 0
                </span>
                <span class="text-xs text-gray-400 dark:text-gray-500">awaiting Sales Orders (Batch 4)</span>
            </div>
        </x-ui.card>

        <x-ui.card title="Active Salesmen">
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-bold text-gray-900 dark:text-gray-50">0</span>
                <span class="text-xs text-gray-400 dark:text-gray-500">awaiting GPS & Field module (Batch 3)</span>
            </div>
        </x-ui.card>

        <x-ui.card title="Open Visits">
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-bold text-gray-900 dark:text-gray-50">0</span>
                <span class="text-xs text-gray-400 dark:text-gray-500">awaiting Visit Planner (Batch 2)</span>
            </div>
        </x-ui.card>

        <x-ui.card title="Collections">
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-bold text-gray-900 dark:text-gray-50">
                    {{ ($tenant?->default_currency ?? 'AFN') === 'AFN' ? '؋' : ($tenant?->default_currency ?? '') }} 0
                </span>
                <span class="text-xs text-gray-400 dark:text-gray-500">awaiting Collections (Batch 4)</span>
            </div>
        </x-ui.card>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-ui.card title="Your role" class="xl:col-span-1">
            <div class="space-y-3 text-sm">
                <p class="text-gray-500 dark:text-gray-400">
                    You are signed in with the <strong class="text-gray-900 dark:text-gray-50">{{ $user->role }}</strong> role.
                </p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($user->roles as $role)
                        <x-ui.status-badge status="role" :label="$role->name" />
                    @endforeach
                </div>
            </div>
        </x-ui.card>

        <x-ui.card title="Company context" class="xl:col-span-2">
            @if ($tenant)
                <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-gray-400 dark:text-gray-500">Company</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-50">{{ $tenant->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-400 dark:text-gray-500">Timezone</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-50">{{ $tenant->timezone }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-400 dark:text-gray-500">Currency</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-50">{{ $tenant->default_currency }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-400 dark:text-gray-500">Subscription</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-50">
                            <x-ui.status-badge :status="$tenant->subscription_status" :label="ucfirst($tenant->subscription_status)" />
                        </dd>
                    </div>
                </dl>
            @else
                <x-ui.empty-state title="Platform mode"
                                  icon="platform"
                                  description="No tenant context is attached to this account. Use the Platform Dashboard to manage organisations." />
            @endif
        </x-ui.card>
    </div>
@endsection