@extends('layouts.app')

@section('title', 'Edit Customer Category')

@section('content')
    <x-ui.page-header title="Edit customer category"
                      description="Update category for {{ $category->name }}.">

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
    </x-ui.page-header>

    <form method="PUT" action="{{ route('customer-categories.update', $category) }}" class="max-w-2xl space-y-6">
        @csrf
        @method('PUT')

        <x-ui.card title="Category details">
            <div class="space-y-5">
                <x-ui.input name="name" label="Name" :value="old('name', $category->name)" required />

                <x-ui.textarea name="description" label="Description" :value="old('description', $category->description)" rows="3" />

                <x-ui.select name="is_active" label="Status" :value="old('is_active', $category->is_active)" :options="[true => 'Active', false => 'Inactive']" />
            </div>

            <x-slot:footer>
                <div class="flex items-center justify-end gap-3">
                    <x-ui.button href="{{ route('customer-categories.show', $category) }}" variant="ghost" type="button">Cancel</x-ui.button>
                    <x-ui.button type="submit">Save changes</x-ui.button>
                </div>
            </x-slot:footer>
        </x-ui.card>
    </form>
@endsection