@extends('layouts.app')

@section('title', 'Add Route')

@section('content')
    <x-ui.page-header title="Add route"
                      description="Create a new route within a territory." />

    <form method="POST" action="{{ route('routes.store') }}" class="max-w-2xl space-y-6">
        @csrf

        <x-ui.card title="Route details">
            <div class="space-y-5">
                <x-ui.select name="territory_id" label="Territory" :value="old('territory_id')" :options="$territories->pluck('name', 'id')->all()" required />

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-ui.input name="code" label="Code" :value="old('code')" required placeholder="e.g. KBL-01-RT" />
                    <x-ui.select name="weekday" label="Weekday" :value="old('weekday')" :options="['' => 'Any day', '0' => 'Sunday', '1' => 'Monday', '2' => 'Tuesday', '3' => 'Wednesday', '4' => 'Thursday', '5' => 'Friday', '6' => 'Saturday']" />
                </div>

                <x-ui.input name="name" label="Name" :value="old('name')" required placeholder="e.g. Kabul City Route" />

                <x-ui.textarea name="description" label="Description" :value="old('description')" rows="3" placeholder="Optional description..." />

                <x-ui.select name="is_active" label="Status" :value="old('is_active', true)" :options="[true => 'Active', false => 'Inactive']" />
            </div>

            <x-slot:footer>
                <div class="flex items-center justify-end gap-3">
                    <x-ui.button href="{{ route('routes.index') }}" variant="ghost" type="button">Cancel</x-ui.button>
                    <x-ui.button type="submit">Create route</x-ui.button>
                </div>
            </x-slot:footer>
        </x-ui.card>
    </form>
@endsection