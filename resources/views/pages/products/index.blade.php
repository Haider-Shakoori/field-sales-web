@extends('layouts.app')

@section('title', 'Products')

@section('content')
    <x-ui.page-header title="Products"
                      description="Manage your product catalog.">
        @if (auth()->user()->hasPermission('products:create'))
            <x-slot:actions>
                <x-ui.button href="{{ route('products.create') }}" icon="plus">Add product</x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <form method="GET" action="{{ route('products.index') }}" class="surface-card p-4">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            <x-ui.input name="search" label="Search" :value="request('search')" placeholder="Name or SKU" />
            <x-ui.select name="category" label="Category" :value="request('category')" :options="['' => 'All categories'] + $categories->flip()->all()" />
            <x-ui.select name="status" label="Status" :value="request('status')" :options="['' => 'Any status', 'active' => 'Active', 'inactive' => 'Inactive']" />
        </div>
        <div class="mt-4 flex items-center justify-end gap-2">
            <x-ui.button href="{{ route('products.index') }}" variant="ghost" type="button">Clear</x-ui.button>
            <x-ui.button type="submit" icon="search">Filter</x-ui.button>
        </div>
    </form>

    <x-ui.card>
        @if ($products->isEmpty())
            <x-ui.empty-state title="No products"
                              icon="products"
                              description="Add products to start building your price lists.">
                @if (auth()->user()->hasPermission('products:create'))
                    <x-slot:action>
                        <x-ui.button href="{{ route('products.create') }}" variant="primary" icon="plus">Add product</x-ui.button>
                    </x-slot:action>
                @endif
            </x-ui.empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-400 dark:border-gray-700 dark:text-gray-500">
                            <th class="px-3 py-3 font-semibold">SKU</th>
                            <th class="px-3 py-3 font-semibold">Name</th>
                            <th class="px-3 py-3 font-semibold">Category</th>
                            <th class="px-3 py-3 font-semibold">Unit</th>
                            <th class="px-3 py-3 font-semibold text-right">Price</th>
                            <th class="px-3 py-3 font-semibold text-right">Lists</th>
                            <th class="px-3 py-3 font-semibold">Status</th>
                            <th class="px-3 py-3 text-right font-semibold"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($products as $product)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                <td class="px-3 py-3 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $product->sku }}</td>
                                <td class="px-3 py-3 font-medium text-gray-900 dark:text-gray-50">{{ $product->name }}</td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $product->category ?? '—' }}</td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ ucfirst($product->unit) }}</td>
                                <td class="px-3 py-3 text-right font-mono text-sm text-gray-900 dark:text-gray-50">{{ number_format($product->price, 2) }}</td>
                                <td class="px-3 py-3 text-right text-gray-500 dark:text-gray-400">{{ $product->price_list_items_count }}</td>
                                <td class="px-3 py-3">
                                    <x-ui.status-badge :status="$product->is_active ? 'active' : 'inactive'" :label="$product->is_active ? 'Active' : 'Inactive'" />
                                </td>
                                <td class="px-3 py-3 text-right">
                                    <a href="{{ route('products.show', $product) }}"
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

            <x-ui.pagination :paginator="$products" />
        @endif
    </x-ui.card>
@endsection