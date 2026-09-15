@extends('layouts.app')

@section('title', 'Add Supervisor')

@section('content')
    <x-ui.page-header title="Add supervisor"
                      description="Create a field supervisor profile." />

    <form method="POST" action="{{ route('supervisors.store') }}" class="max-w-2xl space-y-6">
        @csrf

        <x-ui.card title="Supervisor details">
            <div class="space-y-5">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-ui.input name="first_name" label="First name" :value="old('first_name')" required autofocus />
                    <x-ui.input name="last_name" label="Last name" :value="old('last_name')" />
                </div>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-ui.input name="employee_code" label="Employee code" :value="old('employee_code')"
                                placeholder="Leave blank to auto-generate (e.g. SUP-000001)" />
                    <x-ui.input name="phone" label="Phone" :value="old('phone')" placeholder="+93 ..." />
                </div>

                <x-ui.select name="user_id"
                             label="Linked user"
                             :value="old('user_id')"
                             :options="collect($users)->prepend('Select a user', '')->all()"
                             required />
            </div>

            <x-slot:footer>
                <div class="flex items-center justify-end gap-3">
                    <x-ui.button href="{{ route('supervisors.index') }}" variant="ghost" type="button">Cancel</x-ui.button>
                    <x-ui.button type="submit">Create supervisor</x-ui.button>
                </div>
            </x-slot:footer>
        </x-ui.card>
    </form>
@endsection