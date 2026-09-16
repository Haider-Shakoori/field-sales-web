@extends('layouts.app')

@section('title', 'Customer: {{ $customer->business_name }}')

@section('content')
    <x-ui.page-header title="{{ $customer->business_name }}"
                      description="Customer code: {{ $customer->code }}">
        @if (auth()->user()->hasPermission('customers:update'))
            <x-slot:actions>
                <x-ui.button href="{{ route('customers.edit', $customer) }}" icon="pencil">Edit</x-ui.button>
            </x-slot:actions>
        @endif

        @if (auth()->user()->hasPermission('customers:deactivate'))
            <x-slot:actions>
                <form method="POST" action="{{ route('customers.deactivate', $customer) }}" class="inline" onsubmit="return confirm('Are you sure?')">
                    @csrf
                    <x-ui.button type="submit" variant="{{ $customer->is_active ? 'danger' : 'success' }}" icon="{{ $customer->is_active ? 'user-minus' : 'user-plus' }}">
                        {{ $customer->is_active ? 'Deactivate' : 'Activate' }}
                    </x-ui.button>
                </form>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <x-ui.card title="Basic Information">
                <dl class="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Code</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50 font-mono">{{ $customer->code }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Business Name</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $customer->business_name }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Contact Person</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $customer->contact_person ?? '—' }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Phone</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $customer->phone ?? '—' }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">WhatsApp</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $customer->whatsapp ?? '—' }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Category</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $customer->category?->name ?? '—' }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Province</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $customer->province ?? '—' }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">District</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $customer->district ?? '—' }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Address</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $customer->address ?? '—' }}</dd>
                </dl>
            </x-ui.card>

            <x-ui.card title="Location & Geofence">
                <dl class="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Latitude</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50 font-mono">{{ $customer->latitude ?? '—' }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Longitude</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50 font-mono">{{ $customer->longitude ?? '—' }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Geofence Radius</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $customer->geofence_radius }} meters</dd>

                    @if ($customer->photo_url)
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Photo</dt>
                        <dd class="text-sm text-gray-900 dark:text-gray-50">
                            <a href="{{ $customer->photo_url }}" target="_blank" class="text-blue-600 hover:underline">View Photo</a>
                        </dd>
                    @endif
                </dl>
            </x-ui.card>

            <x-ui.card title="Assignment">
                <dl class="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Branch</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $customer->branch?->name ?? '—' }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Territory</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $customer->territory?->name ?? '—' }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Route</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $customer->route?->name ?? '—' }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Assigned Salesman</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $customer->assignedSalesman ? trim($customer->assignedSalesman->first_name.' '.$customer->assignedSalesman->last_name).' ('.$customer->assignedSalesman->employee_code.')' : 'Unassigned' }}</dd>
                </dl>
            </x-ui.card>

            <x-ui.card title="Commercial">
                <dl class="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Credit Limit</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ number_format($customer->credit_limit, 2) }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Outstanding Balance</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ number_format($customer->outstanding_balance, 2) }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Available Credit</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ number_format($customer->credit_limit - $customer->outstanding_balance, 2) }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Price List</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $customer->priceList?->name ?? 'Default' }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Visit Frequency</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $customer->visit_frequency ? ucfirst(str_replace('_', ' ', $customer->visit_frequency)) : 'Not set' }}</dd>
                </dl>
            </x-ui.card>

            @if ($customer->notes)
                <x-ui.card title="Notes">
                    <div class="prose prose-sm dark:prose-invert max-w-none">
                        {{ nl2br(e($customer->notes)) }}
                    </div>
                </x-ui.card>
            @endif

            @if ($customer->locationHistory->isNotEmpty())
                <x-ui.card title="Location History (Last 10)">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-400 dark:border-gray-700 dark:text-gray-500">
                                    <th class="px-3 py-3 font-semibold">Date/Time</th>
                                    <th class="px-3 py-3 font-semibold">Latitude</th>
                                    <th class="px-3 py-3 font-semibold">Longitude</th>
                                    <th class="px-3 py-3 font-semibold">Address</th>
                                    <th class="px-3 py-3 font-semibold">Changed By</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($customer->locationHistory as $history)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                        <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $history->changed_at->format('Y-m-d H:i:s') }}</td>
                                        <td class="px-3 py-3 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $history->latitude }}</td>
                                        <td class="px-3 py-3 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $history->longitude }}</td>
                                        <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $history->address ?? '—' }}</td>
                                        <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $history->changedBy?->name ?? 'System' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-ui.card>
            @endif
        </div>

        <div class="space-y-6">
            <x-ui.card title="Status">
                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
                        <dd class="text-sm text-gray-900 dark:text-gray-50">
                            <x-ui.status-badge :status="$customer->is_active ? 'active' : 'inactive'" :label="$customer->is_active ? 'Active' : 'Inactive'" />
                        </dd>
                    </div>

                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created</dt>
                        <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $customer->created_at->format('Y-m-d H:i:s') }}</dd>
                    </div>

                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Updated</dt>
                        <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $customer->updated_at->format('Y-m-d H:i:s') }}</dd>
                    </div>

                    @if ($customer->deleted_at)
                        <div class="flex justify-between">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Deactivated</dt>
                            <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $customer->deleted_at->format('Y-m-d H:i:s') }}</dd>
                        </div>
                    @endif
                </dl>
            </x-ui.card>
        </div>
    </div>
@endsection