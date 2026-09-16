@extends('layouts.app')

@section('title', $product->name)

@section('content')
    <x-ui.page-header title="{{ $product->name }}"
                      description="SKU: {{ $product->sku }} — {{ ucfirst($product->unit) }}">
        @if (auth()->user()->hasPermission('products:update'))
            <x-slot:actions>
                <x-ui.button href="{{ route('products.edit', $product) }}" icon="pencil">Edit</x-ui.button>
            </x-slot:actions>
        @endif

        @if (auth()->user()->hasPermission('products:deactivate'))
            <x-slot:actions>
                <form method="POST" action="{{ route('products.deactivate', $product) }}" class="inline" onsubmit="return confirm('Are you sure?')">
                    @csrf
                    <x-ui.button type="submit" variant="{{ $product->is_active ? 'danger' : 'success' }}">
                        {{ $product->is_active ? 'Deactivate' : 'Activate' }}
                    </x-ui.button>
                </form>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <x-ui.card title="Product Details">
                <dl class="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">SKU</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50 font-mono">{{ $product->sku }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Name</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $product->name }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Category</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $product->category ?? '—' }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Unit</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ ucfirst($product->unit) }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Base price</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50 font-mono">{{ number_format($product->price, 2) }}</dd>
                </dl>
            </x-ui.card>

            <x-ui.card title="Price Lists ({{ $product->priceListItems->count() }})">
                @if ($product->priceListItems->isEmpty())
                    <p class="text-gray-500 dark:text-gray-400 py-4">This product is not yet added to any price list.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-400 dark:border-gray-700 dark:text-gray-500">
                                    <th class="px-3 py-3 font-semibold">Price List</th>
                                    <th class="px-3 py-3 font-semibold text-right">Override Price</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($product->priceListItems as $item)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                        <td class="px-3 py-3">
                                            <a href="{{ route('price-lists.show', $item->priceList) }}" class="font-medium text-blue-600 hover:underline dark:text-blue-400">
                                                {{ $item->priceList->name }}
                                                @if ($item->priceList->is_default) <span class="ml-1 text-xs text-gray-400">(Default)</span> @endif
                                            </a>
                                        </td>
                                        <td class="px-3 py-3 text-right font-mono text-sm">{{ number_format($item->price, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <x-ui.card title="Status">
                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
                        <dd class="text-sm text-gray-900 dark:text-gray-50">
                            <x-ui.status-badge :status="$product->is_active ? 'active' : 'inactive'" :label="$product->is_active ? 'Active' : 'Inactive'" />
                        </dd>
                    </div>

                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created</dt>
                        <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $product->created_at->format('Y-m-d H:i:s') }}</dd>
                    </div>

                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Updated</dt>
                        <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $product->updated_at->format('Y-m-d H:i:s') }}</dd>
                    </div>
                </dl>
            </x-ui.card>
        </div>
    </div>
@endsection