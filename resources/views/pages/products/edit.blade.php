@extends('layouts.app')

@section('title', 'Edit: '.$product->name)

@section('content')
    <x-ui.page-header title="Edit product"
                      description="SKU: {{ $product->sku }}" />

    <form method="POST" action="{{ route('products.update', $product) }}" class="max-w-4xl space-y-6">
        @csrf
        @method('PUT')

        <x-ui.card title="Product Details">
            <div class="space-y-5">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-ui.input name="sku" label="SKU" :value="old('sku', $product->sku)" required />
                    <x-ui.select name="unit" label="Unit" :value="old('unit', $product->unit)" :options="['piece' => 'Piece', 'box' => 'Box', 'case' => 'Case', 'kg' => 'Kg', 'litre' => 'Litre']" required />
                </div>

                <x-ui.input name="name" label="Name" :value="old('name', $product->name)" required />

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-ui.input name="price" type="number" step="0.01" min="0" label="Base price" :value="old('price', $product->price)" required />
                    <x-ui.input name="category" label="Category" :value="old('category', $product->category)" placeholder="Free-text, e.g. Food, Beverage" />
                </div>
            </div>

            <x-slot:footer>
                <div class="flex items-center justify-end gap-3">
                    <x-ui.button href="{{ route('products.show', $product) }}" variant="ghost" type="button">Cancel</x-ui.button>
                    <x-ui.button type="submit">Save changes</x-ui.button>
                </div>
            </x-slot:footer>
        </x-ui.card>
    </form>
@endsection