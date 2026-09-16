@extends('layouts.app')

@section('title', 'Edit Territory')

@section('content')
    <x-ui.page-header title="Edit territory"
                      description="Update territory for {{ $territory->name }}.">

        @if (auth()->user()->hasPermission('territories:deactivate'))
            <x-slot:actions>
                <form method="POST" action="{{ route('territories.deactivate', $territory) }}" class="inline" onsubmit="return confirm('Are you sure?')">
                    @csrf
                    <x-ui.button type="submit" variant="{{ $territory->is_active ? 'danger' : 'success' }}" icon="{{ $territory->is_active ? 'map-pin-off' : 'map-pin' }}">
                        {{ $territory->is_active ? 'Deactivate' : 'Activate' }}
                    </x-ui.button>
                </form>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <form method="PUT" action="{{ route('territories.update', $territory) }}" class="max-w-2xl space-y-6">
        @csrf
        @method('PUT')

        <x-ui.card title="Territory details">
            <div class="space-y-5">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-ui.select name="branch_id" label="Branch" :value="old('branch_id', $territory->branch_id)" :options="$branches->pluck('name', 'id')->all()" required />
                    <x-ui.input name="code" label="Code" :value="old('code', $territory->code)" required />
                </div>

                <x-ui.input name="name" label="Name" :value="old('name', $territory->name)" required />

                <x-ui.textarea name="description" label="Description" :value="old('description', $territory->description)" rows="3" />

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                    <x-ui.input name="latitude" type="number" step="0.0000001" label="Latitude" :value="old('latitude', $territory->latitude)" placeholder="e.g. 34.5553" />
                    <x-ui.input name="longitude" type="number" step="0.0000001" label="Longitude" :value="old('longitude', $territory->longitude)" placeholder="e.g. 69.2075" />
                    <x-ui.input name="radius_km" type="number" step="0.01" label="Radius (km)" :value="old('radius_km', $territory->radius_km)" min="0" max="999.99" />
                </div>

                <x-ui.select name="is_active" label="Status" :value="old('is_active', $territory->is_active)" :options="[true => 'Active', false => 'Inactive']" />
            </div>

            <x-slot:footer>
                <div class="flex items-center justify-end gap-3">
                    <x-ui.button href="{{ route('territories.show', $territory) }}" variant="ghost" type="button">Cancel</x-ui.button>
                    <x-ui.button type="submit">Save changes</x-ui.button>
                </div>
            </x-slot:footer>
        </x-ui.card>
    </form>
@endsection