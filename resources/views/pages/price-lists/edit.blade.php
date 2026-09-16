@extends('layouts.app')

@section('title', 'Edit: '.$priceList->name)

@section('content')
    <x-ui.page-header title="Edit price list"
                      description="{{ $priceList->is_default ? 'Default list' : 'Standard list' }}" />

    <form method="POST" action="{{ route('price-lists.update', $priceList) }}" class="max-w-4xl space-y-6">
        @csrf
        @method('PUT')

        <x-ui.card title="Price List Details">
            <div class="space-y-5">
                <x-ui.input name="name" label="Name" :value="old('name', $priceList->name)" required />

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-ui.select name="is_default" label="Default price list?" :value="old('is_default', $priceList->is_default ? '1' : '0')" :options="['0' => 'No', '1' => 'Yes']" />
                    @if ($priceList->is_default)
                        <div class="text-xs text-amber-600 dark:text-amber-400 mt-6">This is the current default list for the tenant.</div>
                    @endif
                </div>
            </div>

            <x-slot:footer>
                <div class="flex items-center justify-end gap-3">
                    <x-ui.button href="{{ route('price-lists.show', $priceList) }}" variant="ghost" type="button">Cancel</x-ui.button>
                    <x-ui.button type="submit">Save changes</x-ui.button>
                </div>
            </x-slot:footer>
        </x-ui.card>
    </form>
@endsection