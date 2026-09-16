@extends('layouts.app')

@section('title', 'Category: {{ $category->name }}')

@section('content')
    <x-ui.page-header title="{{ $category->name }}"
                      description="{{ $category->customers_count }} customers">

        @if (auth()->user()->hasPermission('customer_categories:update'))
            <x-slot:actions>
                <x-ui.button href="{{ route('customer-categories.edit', $category) }}" icon="pencil">Edit</x-ui.button>
            </x-slot:actions>
        @endif

        @if (auth()->user()->hasPermission('customer_categories:deactivate'))
            <x-slot:actions>
                <form method="POST" action="{{ route('customer-categories.deactivate', $category) }}" class="inline" onsubmit="return confirm('Are you sure?')">
                    @csrf
                    <x-ui.button type="submit" variant="{{ $category->is_active ? 'danger' : 'success' }}" icon="{{ $category->is_active ? 'user-minus' : 'user-plus' }}">
                        {{ $category->is_active ? 'Deactivate' : 'Activate' }}
                    </x-ui.button>
                </form>
            </x-slot:actions>
        @endif

        @if (auth()->user()->hasPermission('customer_categories:delete') && $category->customers_count === 0)
            <x-slot:actions>
                <form method="POST" action="{{ route('customer-categories.destroy', $category) }}" class="inline" onsubmit="return confirm('Permanently delete this category?')">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit" variant="danger" icon="trash">Delete</x-ui.button>
                </form>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <x-ui.card title="Category Details">
                <dl class="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Name</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $category->name }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Description</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50 sm:col-span-2">{{ $category->description ?? '—' }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Customers</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $category->customers_count }}</dd>
                </dl>
            </x-ui.card>

            @if ($category->customers_count > 0)
                <x-ui.card title="Customers in this Category">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-400 dark:border-gray-700 dark:text-gray-500">
                                    <th class="px-3 py-3 font-semibold">Code</th>
                                    <th class="px-3 py-3 font-semibold">Business Name</th>
                                    <th class="px-3 py-3 font-semibold">Contact</th>
                                    <th class="px-3 py-3 font-semibold">Branch</th>
                                    <th class="px-3 py-3 font-semibold">Status</th>
                                    <th class="px-3 py-3 text-right font-semibold"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($category->customers as $customer)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                        <td class="px-3 py-3 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $customer->code }}</td>
                                        <td class="px-3 py-3 font-medium text-gray-900 dark:text-gray-50">{{ $customer->business_name }}</td>
                                        <td class="px-3 py-3 text-gray-500 dark:text-gray-400">
                                            {{ $customer->contact_person ?? '—' }}<br>
                                            {{ $customer->phone ?? '—' }}
                                        </td>
                                        <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $customer->branch?->name ?? '—' }}</td>
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
                </x-ui.card>
            @endif
        </div>

        <div class="space-y-6">
            <x-ui.card title="Status">
                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
                        <dd class="text-sm text-gray-900 dark:text-gray-50">
                            <x-ui.status-badge :status="$category->is_active ? 'active' : 'inactive'" :label="$category->is_active ? 'Active' : 'Inactive'" />
                        </dd>
                    </div>

                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created</dt>
                        <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $category->created_at->format('Y-m-d H:i:s') }}</dd>
                    </div>

                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Updated</dt>
                        <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $category->updated_at->format('Y-m-d H:i:s') }}</dd>
                    </div>
                </dl>
            </x-ui.card>
        </div>
    </div>
@endsection