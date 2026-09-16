@extends('layouts.app')

@section('title', 'Customer Categories')

@section('content')
    <x-ui.page-header title="Customer Categories"
                      description="Categorization for customers (e.g., wholesale, retail, key account).">
        @if (auth()->user()->hasPermission('customer_categories:create'))
            <x-slot:actions>
                <x-ui.button href="{{ route('customer-categories.create') }}" icon="plus">Add category</x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <form method="GET" action="{{ route('customer-categories.index') }}" class="surface-card p-4">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-ui.input name="search" label="Search" :value="request('search')" placeholder="Name or description" />
            <x-ui.select name="status" label="Status" :value="request('status')" :options="['' => 'Any status', 'active' => 'Active', 'inactive' => 'Inactive']" />
        </div>
        <div class="mt-4 flex items-center justify-end gap-2">
            <x-ui.button href="{{ route('customer-categories.index') }}" variant="ghost" type="button">Clear</x-ui.button>
            <x-ui.button type="submit" icon="search">Filter</x-ui.button>
        </div>
    </form>

    <x-ui.card>
        @if ($categories->isEmpty())
            <x-ui.empty-state title="No customer categories"
                              icon="category"
                              description="Add categories to organize your customers.">
                @if (auth()->user()->hasPermission('customer_categories:create'))
                    <x-slot:action>
                        <x-ui.button href="{{ route('customer-categories.create') }}" variant="primary" icon="plus">Add category</x-ui.button>
                    </x-slot:action>
                @endif
            </x-ui.empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-400 dark:border-gray-700 dark:text-gray-500">
                            <th class="px-3 py-3 font-semibold">Name</th>
                            <th class="px-3 py-3 font-semibold">Description</th>
                            <th class="px-3 py-3 font-semibold">Customers</th>
                            <th class="px-3 py-3 font-semibold">Status</th>
                            <th class="px-3 py-3 text-right font-semibold"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($categories as $category)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                <td class="px-3 py-3 font-medium text-gray-900 dark:text-gray-50">{{ $category->name }}</td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $category->description ?? '—' }}</td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $category->customers_count }}</td>
                                <td class="px-3 py-3">
                                    <x-ui.status-badge :status="$category->is_active ? 'active' : 'inactive'" :label="$category->is_active ? 'Active' : 'Inactive'" />
                                </td>
                                <td class="px-3 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('customer-categories.show', $category) }}"
                                           class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-500/10">
                                            <x-ui.icon name="eye" class="h-3.5 w-3.5" />
                                            View
                                        </a>
                                        @if (auth()->user()->hasPermission('customer_categories:update'))
                                            <a href="{{ route('customer-categories.edit', $category) }}"
                                               class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-500/10">
                                                <x-ui.icon name="pencil" class="h-3.5 w-3.5" />
                                            </a>
                                        @endif
                                        @if (auth()->user()->hasPermission('customer_categories:deactivate'))
                                            <form method="POST" action="{{ route('customer-categories.deactivate', $category) }}" class="inline" onsubmit="return confirm('Are you sure?')">
                                                @csrf
                                                <x-ui.button type="submit" variant="{{ $category->is_active ? 'danger' : 'success' }}" size="sm" icon="{{ $category->is_active ? 'user-minus' : 'user-plus' }}" class="p-1">
                                                </x-ui.button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.pagination :paginator="$categories" />
        @endif
    </x-ui.card>
@endsection