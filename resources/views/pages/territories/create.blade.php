@extends('layouts.app')

@section('title', 'Add Territory')

@section('content')
    <x-ui.page-header title="Add territory"
                      description="Create a new geographic territory." />

    <form method="POST" action="{{ route('territories.store') }}" class="max-w-2xl space-y-6">
        @csrf

        <x-ui.card title="Territory details">
            <div class="space-y-5">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-ui.select name="branch_id" label="Branch" :value="old('branch_id')" :options="$branches->pluck('name', 'id')->all()" required />
                    <x-ui.input name="code" label="Code" :value="old('code')" required placeholder="e.g. KBL-01" />
                </div>

                <x-ui.input name="name" label="Name" :value="old('name')" required placeholder="e.g. Kabul City" />

                <x-ui.textarea name="description" label="Description" :value="old('description')" rows="3" placeholder="Optional description..." />

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                    <x-ui.input name="latitude" type="number" step="0.0000001" label="Latitude" :value="old('latitude')" placeholder="e.g. 34.5553" />
                    <x-ui.input name="longitude" type="number" step="0.0000001" label="Longitude" :value="old('longitude')" placeholder="e.g. 69.2075" />
                    <x-ui.input name="radius_km" type="number" step="0.01" label="Radius (km)" :value="old('radius_km')" min="0" max="999.99" placeholder="e.g. 15.5" />
                </div>

                <x-ui.select name="is_active" label="Status" :value="old('is_active', true)" :options="[true => 'Active', false => 'Inactive']" />
            </div>

            <x-slot:footer>
                <div class="flex items-center justify-end gap-3">
                    <x-ui.button href="{{ route('territories.index') }}" variant="ghost" type="button">Cancel</x-ui.button>
                    <x-ui.button type="submit">Create territory</x-ui.button>
                </div>
            </x-slot:footer>
        </x-ui.card>
    </form>
@endsection