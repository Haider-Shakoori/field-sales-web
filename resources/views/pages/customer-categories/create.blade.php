@extends('layouts.app')

@section('title', 'Add Customer Category')

@section('content')
    <x-ui.page-header title="Add customer category"
                      description="Create a new customer category." />

    <form method="POST" action="{{ route('customer-categories.store') }}" class="max-w-2xl space-y-6">
        @csrf

        <x-ui.card title="Category details">
            <div class="space-y-5">
                <x-ui.input name="name" label="Name" :value="old('name')" required placeholder="e.g. Wholesale" />

                <x-ui.textarea name="description" label="Description" :value="old('description')" rows="3" placeholder="Optional description..." />

                <x-ui.select name="is_active" label="Status" :value="old('is_active', true)" :options="[true => 'Active', false => 'Inactive']" />
            </div>

            <x-slot:footer>
                <div class="flex items-center justify-end gap-3">
                    <x-ui.button href="{{ route('customer-categories.index') }}" variant="ghost" type="button">Cancel</x-ui.button>
                    <x-ui.button type="submit">Create category</x-ui.button>
                </div>
            </x-slot:footer>
        </x-ui.card>
    </form>
@endsection