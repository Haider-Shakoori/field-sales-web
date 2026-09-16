@extends('layouts.app')

@section('title', $priceList->name)

@section('content')
    <x-ui.page-header title="{{ $priceList->name }}"
                      description="{{ $priceList->is_default ? 'Default list' : 'Standard list' }}">
        @if (auth()->user()->hasPermission('price_lists:update'))
            <x-slot:actions>
                <x-ui.button href="{{ route('price-lists.edit', $priceList) }}" icon="pencil">Edit</x-ui.button>
            </x-slot:actions>
        @endif

        @if (auth()->user()->hasPermission('price_lists:deactivate'))
            <x-slot:actions>
                <form method="POST" action="{{ route('price-lists.deactivate', $priceList) }}" class="inline" onsubmit="return confirm('Are you sure?')">
                    @csrf
                    <x-ui.button type="submit" variant="{{ $priceList->is_active ? 'danger' : 'success' }}">
                        {{ $priceList->is_active ? 'Deactivate' : 'Activate' }}
                    </x-ui.button>
                </form>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    @if ($errors->any())
        <div class="mb-6 rounded border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/30 dark:text-red-300">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <x-ui.card title="Price List Details">
                <dl class="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Name</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $priceList->name }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Default</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $priceList->is_default ? 'Yes' : 'No' }}</dd>
                </dl>
            </x-ui.card>

            @if (auth()->user()->hasPermission('price_lists:manage_prices'))
                <x-ui.card title="Add Product to Price List">
                    <form method="POST" action="{{ route('price-lists.items.store', $priceList) }}" class="grid grid-cols-1 gap-4 sm:grid-cols-4 items-end">
                        @csrf
                        <div class="sm:col-span-2">
                            <x-ui.select name="product_id" label="Product" value="{{ old('product_id') }}" :options="$products->pluck('name', 'id')->all()" required />
                        </div>
                        <div>
                            <x-ui.input name="price" type="number" step="0.01" min="0" label="Override price" value="{{ old('price') }}" required />
                        </div>
                        <div>
                            <x-ui.button type="submit" icon="plus" class="w-full">Add</x-ui.button>
                        </div>
                    </form>
                </x-ui.card>
            @endif

            <x-ui.card title="Products ({{ $priceList->items->count() }})">
                @if ($priceList->items->isEmpty())
                    <p class="text-gray-500 dark:text-gray-400 py-4">No products priced in this list.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-400 dark:border-gray-700 dark:text-gray-500">
                                    <th class="px-3 py-3 font-semibold">SKU</th>
                                    <th class="px-3 py-3 font-semibold">Product</th>
                                    <th class="px-3 py-3 font-semibold text-right">Override Price</th>
                                    <th class="px-3 py-3 text-right font-semibold"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800" x-data="{ editing: null }">
                                @foreach ($priceList->items as $item)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                        <td class="px-3 py-3 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $item->product->sku }}</td>
                                        <td class="px-3 py-3 font-medium text-gray-900 dark:text-gray-50">{{ $item->product->name }}</td>
                                        <td class="px-3 py-3 text-right font-mono text-sm text-gray-900 dark:text-gray-50">
                                            <span x-show="editing !== {{ $item->id }}">{{ number_format($item->price, 2) }}</span>
                                            <form x-show="editing === {{ $item->id }}" method="POST" action="{{ route('price-lists.items.update', $item) }}" class="inline-flex items-center gap-2">
                                                @csrf
                                                @method('PUT')
                                                <input type="number" name="price" step="0.01" min="0" value="{{ $item->price }}" class="w-24 surface-input rounded px-2 py-1 text-sm" required />
                                                <x-ui.button type="submit" variant="ghost" class="!p-1">Save</x-ui.button>
                                                <button type="button" x-on:click="editing = null" class="text-gray-400 hover:text-gray-600 text-xs">Cancel</button>
                                            </form>
                                        </td>
                                        <td class="px-3 py-3 text-right space-x-2" x-show="editing !== {{ $item->id }}">
                                            <button type="button" x-on:click="editing = {{ $item->id }}" class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-500/10">
                                                <x-ui.icon name="pencil" class="h-3.5 w-3.5" />
                                                Edit
                                            </button>
                                            <form method="POST" action="{{ route('price-lists.items.destroy', $item) }}" class="inline" onsubmit="return confirm('Remove this product from the price list?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10">
                                                    <x-ui.icon name="trash" class="h-3.5 w-3.5" />
                                                    Remove
                                                </button>
                                            </form>
                                        </td>
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
                            <x-ui.status-badge :status="$priceList->is_active ? 'active' : 'inactive'" :label="$priceList->is_active ? 'Active' : 'Inactive'" />
                        </dd>
                    </div>

                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created</dt>
                        <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $priceList->created_at->format('Y-m-d H:i:s') }}</dd>
                    </div>

                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Updated</dt>
                        <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $priceList->updated_at->format('Y-m-d H:i:s') }}</dd>
                    </div>
                </dl>
            </x-ui.card>
        </div>
    </div>
@endsection