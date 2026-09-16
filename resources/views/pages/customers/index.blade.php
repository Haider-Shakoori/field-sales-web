@extends('layouts.app')

@section('title', 'Customers')

@section('content')
    <x-ui.page-header title="Customers"
                      description="Manage customer master data, contacts, and assignments.">
        @if (auth()->user()->hasPermission('customers:create'))
            <x-slot:actions>
                <x-ui.button href="{{ route('customers.create') }}" icon="plus">Add customer</x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <form method="GET" action="{{ route('customers.index') }}" class="surface-card p-4">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-6">
            <x-ui.input name="search" label="Search" :value="request('search')" placeholder="Name, code, contact, phone" />
            <x-ui.select name="branch_id" label="Branch" :value="request('branch_id')" :options="['' => 'All branches'] + $branches->pluck('name', 'id')->all()" />
            <x-ui.select name="category_id" label="Category" :value="request('category_id')" :options="['' => 'All categories'] + $categories->pluck('name', 'id')->all()" />
            <x-ui.select name="territory_id" label="Territory" :value="request('territory_id')" :options="['' => 'All territories'] + $territories->pluck('name', 'id')->all()" />
            <x-ui.select name="route_id" label="Route" :value="request('route_id')" :options="['' => 'All routes'] + $routes->pluck('name', 'id')->all()" />
            <x-ui.select name="assigned_salesman_id" label="Assigned Salesman" :value="request('assigned_salesman_id')" :options="['' => 'All salesmen'] + $salesmen->mapWithKeys(fn ($s) => [$s->id => trim($s->first_name.' '.$s->last_name).' ('.$s->employee_code.')'])->all()" />
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 mt-4">
            <x-ui.select name="status" label="Status" :value="request('status')" :options="['' => 'Any status', 'active' => 'Active', 'inactive' => 'Inactive']" />
        </div>
        <div class="mt-4 flex items-center justify-end gap-2">
            <x-ui.button href="{{ route('customers.index') }}" variant="ghost" type="button">Clear</x-ui.button>
            <x-ui.button type="submit" icon="search">Filter</x-ui.button>
        </div>
    </form>

    <x-ui.card>
        @if ($customers->isEmpty())
            <x-ui.empty-state title="No customers"
                              icon="customers"
                              description="Add customers to manage their profiles, contacts, and assignments.">
                @if (auth()->user()->hasPermission('customers:create'))
                    <x-slot:action>
                        <x-ui.button href="{{ route('customers.create') }}" variant="primary" icon="plus">Add customer</x-ui.button>
                    </x-slot:action>
                @endif
            </x-ui.empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-400 dark:border-gray-700 dark:text-gray-500">
                            <th class="px-3 py-3 font-semibold">Code</th>
                            <th class="px-3 py-3 font-semibold">Business Name</th>
                            <th class="px-3 py-3 font-semibold">Contact</th>
                            <th class="px-3 py-3 font-semibold">Branch</th>
                            <th class="px-3 py-3 font-semibold">Territory</th>
                            <th class="px-3 py-3 font-semibold">Route</th>
                            <th class="px-3 py-3 font-semibold">Salesman</th>
                            <th class="px-3 py-3 font-semibold">Status</th>
                            <th class="px-3 py-3 text-right font-semibold"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($customers as $customer)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                <td class="px-3 py-3 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $customer->code }}</td>
                                <td class="px-3 py-3 font-medium text-gray-900 dark:text-gray-50">{{ $customer->business_name }}</td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">
                                    @if ($customer->contact_person)
                                        {{ $customer->contact_person }}<br>
                                    @endif
                                    {{ $customer->phone ?? '—' }}
                                </td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $customer->branch?->name ?? '—' }}</td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $customer->territory?->name ?? '—' }}</td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $customer->route?->name ?? '—' }}</td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $customer->assignedSalesman ? trim($customer->assignedSalesman->first_name.' '.$customer->assignedSalesman->last_name) : '—' }}</td>
                                <td class="px-3 py-3">
                                    <x-ui.status-badge :status="$customer->is_active ? 'active' : 'inactive'" :label="$customer->is_active ? 'Active' : 'Inactive'" />
                                </td>
                                <td class="px-3 py-3 text-right">
                                    <a href="{{ route('customers.show', $customer) }}"
                                       class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-500/10">
                                        <x-ui.icon name="eye" class="h-3.5 w-3.5" />
                                        View
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.pagination :paginator="$customers" />
        @endif
    </x-ui.card>
@endsection