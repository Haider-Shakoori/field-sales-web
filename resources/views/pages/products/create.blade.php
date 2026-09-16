@extends('layouts.app')

@section('title', 'Add Product')

@section('content')
    <x-ui.page-header title="Add product"
                      description="Create a new product in your catalog." />

    <form method="POST" action="{{ route('products.store') }}" class="max-w-4xl space-y-6">
        @csrf

        <x-ui.card title="Product Details">
            <div class="space-y-5">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-ui.input name="sku" label="SKU" :value="old('sku')" required placeholder="Unique stock keeping unit" />
                    <x-ui.select name="unit" label="Unit" :value="old('unit', 'piece')" :options="['piece' => 'Piece', 'box' => 'Box', 'case' => 'Case', 'kg' => 'Kg', 'litre' => 'Litre']" required />
                </div>

                <x-ui.input name="name" label="Name" :value="old('name')" required />

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-ui.input name="price" type="number" step="0.01" min="0" label="Base price" :value="old('price', '0.00')" required />
                    <x-ui.input name="category" label="Category" :value="old('category')" placeholder="Free-text, e.g. Food, Beverage" />
                </div>
            </div>

            <x-slot:footer>
                <div class="flex items-center justify-end gap-3">
                    <x-ui.button href="{{ route('products.index') }}" variant="ghost" type="button">Cancel</x-ui.button>
                    <x-ui.button type="submit">Create product</x-ui.button>
                </div>
            </x-slot:footer>
        </x-ui.card>
    </form>
@endsection