@extends('layouts.app')

@section('title', 'Platform')

@section('content')
    <x-ui.page-header title="Platform Dashboard"
                      description="Super-admin overview across all organisations." />

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
        <x-ui.stat-card label="Tenants"
                        :value="$summary['tenants']"
                        description="Organisations"
                        icon="building"
                        tone="blue" />

        <x-ui.stat-card label="Members"
                        :value="$summary['users']"
                        description="Users across all organisations"
                        icon="user"
                        tone="green" />

        <x-ui.stat-card label="Trials"
                        :value="$summary['active_trials']"
                        description="On trial"
                        icon="platform"
                        tone="amber" />
    </div>

    <x-ui.card title="All organisations">
        @if ($tenants->isEmpty())
            <x-ui.empty-state title="No tenants"
                              icon="building"
                              description="Provision the first organisation to get started." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-400 dark:border-gray-700 dark:text-gray-500">
                            <th class="px-3 py-3 font-semibold">Company</th>
                            <th class="px-3 py-3 font-semibold">Slug</th>
                            <th class="px-3 py-3 font-semibold">Timezone</th>
                            <th class="px-3 py-3 font-semibold">Members</th>
                            <th class="px-3 py-3 font-semibold">Branches</th>
                            <th class="px-3 py-3 font-semibold">Subscription</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($tenants as $tenant)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                <td class="px-3 py-3 font-medium text-gray-900 dark:text-gray-50">{{ $tenant->name }}</td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $tenant->slug }}</td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $tenant->timezone }}</td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $tenant->users_count }}</td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $tenant->branches_count }}</td>
                                <td class="px-3 py-3">
                                    <x-ui.status-badge :status="$tenant->subscription_status" :label="ucfirst($tenant->subscription_status)" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.pagination :paginator="$tenants" />
        @endif
    </x-ui.card>
@endsection