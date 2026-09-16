@extends('layouts.app')

@section('title', 'Price Lists')

@section('content')
    <x-ui.page-header title="Price Lists"
                      description="Manage price lists and per-product overrides.">
        @if (auth()->user()->hasPermission('price_lists:create'))
            <x-slot:actions>
                <x-ui.button href="{{ route('price-lists.create') }}" icon="plus">Add price list</x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <form method="GET" action="{{ route('price-lists.index') }}" class="surface-card p-4">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            <x-ui.input name="search" label="Search" :value="request('search')" placeholder="List name" />
            <x-ui.select name="status" label="Status" :value="request('status')" :options="['' => 'Any status', 'active' => 'Active', 'inactive' => 'Inactive']" />
        </div>
        <div class="mt-4 flex items-center justify-end gap-2">
            <x-ui.button href="{{ route('price-lists.index') }}" variant="ghost" type="button">Clear</x-ui.button>
            <x-ui.button type="submit" icon="search">Filter</x-ui.button>
        </div>
    </form>

    <x-ui.card>
        @if ($priceLists->isEmpty())
            <x-ui.empty-state title="No price lists"
                              icon="price-lists"
                              description="Create a price list to start assigning product prices to customers.">
                @if (auth()->user()->hasPermission('price_lists:create'))
                    <x-slot:action>
                        <x-ui.button href="{{ route('price-lists.create') }}" variant="primary" icon="plus">Add price list</x-ui.button>
                    </x-slot:action>
                @endif
            </x-ui.empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-400 dark:border-gray-700 dark:text-gray-500">
                            <th class="px-3 py-3 font-semibold">Name</th>
                            <th class="px-3 py-3 font-semibold text-center">Default</th>
                            <th class="px-3 py-3 font-semibold">Status</th>
                            <th class="px-3 py-3 font-semibold text-right">Items</th>
                            <th class="px-3 py-3 text-right font-semibold"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($priceLists as $priceList)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                <td class="px-3 py-3 font-medium text-gray-900 dark:text-gray-50">
                                    {{ $priceList->name }}
                                    @if ($priceList->is_default)
                                        <span class="ml-2 inline-block rounded bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700 dark:bg-blue-500/15 dark:text-blue-300">Default</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-center text-gray-500 dark:text-gray-400">{{ $priceList->is_default ? 'Yes' : 'No' }}</td>
                                <td class="px-3 py-3">
                                    <x-ui.status-badge :status="$priceList->is_active ? 'active' : 'inactive'" :label="$priceList->is_active ? 'Active' : 'Inactive'" />
                                </td>
                                <td class="px-3 py-3 text-right text-gray-500 dark:text-gray-400">{{ $priceList->items_count ?? $priceList->items_count }}</td>
                                <td class="px-3 py-3 text-right">
                                    <a href="{{ route('price-lists.show', $priceList) }}"
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

            <x-ui.pagination :paginator="$priceLists" />
        @endif
    </x-ui.card>
@endsection