@extends('layouts.app')

@section('title', 'Add Price List')

@section('content')
    <x-ui.page-header title="Add price list"
                      description="Create a new tenant price list." />

    <form method="POST" action="{{ route('price-lists.store') }}" class="max-w-4xl space-y-6">
        @csrf

        <x-ui.card title="Price List Details">
            <div class="space-y-5">
                <x-ui.input name="name" label="Name" :value="old('name')" required placeholder="e.g. Wholesale, Retail" />

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-ui.select name="is_default" label="Set as default?" :value="old('is_default', '0')" :options="['0' => 'No', '1' => 'Yes']" />
                    <div class="text-xs text-gray-400 dark:text-gray-500 mt-6">Marking this as default will replace the current default list.</div>
                </div>
            </div>

            <x-slot:footer>
                <div class="flex items-center justify-end gap-3">
                    <x-ui.button href="{{ route('price-lists.index') }}" variant="ghost" type="button">Cancel</x-ui.button>
                    <x-ui.button type="submit">Create price list</x-ui.button>
                </div>
            </x-slot:footer>
        </x-ui.card>
    </form>
@endsection