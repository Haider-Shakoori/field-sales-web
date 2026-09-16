@extends('layouts.app')

@section('title', 'Edit Route')

@section('content')
    <x-ui.page-header title="Edit route"
                      description="Update route for {{ $route->name }}.">

        @if (auth()->user()->hasPermission('routes:deactivate'))
            <x-slot:actions>
                <form method="POST" action="{{ route('routes.deactivate', $route) }}" class="inline" onsubmit="return confirm('Are you sure?')">
                    @csrf
                    <x-ui.button type="submit" variant="{{ $route->is_active ? 'danger' : 'success' }}" icon="{{ $route->is_active ? 'map-pin-off' : 'map-pin' }}">
                        {{ $route->is_active ? 'Deactivate' : 'Activate' }}
                    </x-ui.button>
                </form>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <form method="PUT" action="{{ route('routes.update', $route) }}" class="max-w-2xl space-y-6">
        @csrf
        @method('PUT')

        <x-ui.card title="Route details">
            <div class="space-y-5">
                <x-ui.select name="territory_id" label="Territory" :value="old('territory_id', $route->territory_id)" :options="$territories->pluck('name', 'id')->all()" required />

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-ui.input name="code" label="Code" :value="old('code', $route->code)" required />
                    <x-ui.select name="weekday" label="Weekday" :value="old('weekday', $route->weekday)" :options="['' => 'Any day', '0' => 'Sunday', '1' => 'Monday', '2' => 'Tuesday', '3' => 'Wednesday', '4' => 'Thursday', '5' => 'Friday', '6' => 'Saturday']" />
                </div>

                <x-ui.input name="name" label="Name" :value="old('name', $route->name)" required />

                <x-ui.textarea name="description" label="Description" :value="old('description', $route->description)" rows="3" />

                <x-ui.select name="is_active" label="Status" :value="old('is_active', $route->is_active)" :options="[true => 'Active', false => 'Inactive']" />
            </div>

            <x-slot:footer>
                <div class="flex items-center justify-end gap-3">
                    <x-ui.button href="{{ route('routes.show', $route) }}" variant="ghost" type="button">Cancel</x-ui.button>
                    <x-ui.button type="submit">Save changes</x-ui.button>
                </div>
            </x-slot:footer>
        </x-ui.card>
    </form>
@endsection