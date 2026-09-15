@extends('layouts.app')

@section('title', 'Add Salesman')

@section('content')
    <x-ui.page-header title="Add salesman"
                      description="Create a field sales representative profile." />

    <form method="POST" action="{{ route('salesmen.store') }}" class="max-w-2xl space-y-6">
        @csrf

        <x-ui.card title="Salesman details">
            <div class="space-y-5">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-ui.input name="first_name" label="First name" :value="old('first_name')" required autofocus />
                    <x-ui.input name="last_name" label="Last name" :value="old('last_name')" />
                </div>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-ui.input name="employee_code" label="Employee code" :value="old('employee_code')"
                                placeholder="Leave blank to auto-generate (e.g. SLM-000001)" />
                    <x-ui.input name="phone" label="Phone" :value="old('phone')" placeholder="+93 ..." />
                </div>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-ui.input name="email" type="email" label="Email" :value="old('email')" />
                    <x-ui.input name="designation" label="Designation" :value="old('designation')" placeholder="e.g. Senior Sales Rep" />
                </div>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-ui.input name="hire_date" type="date" label="Hire date" :value="old('hire_date')" />
                    <x-ui.select name="user_id"
                                 label="Linked user (optional)"
                                 :value="old('user_id')"
                                 :options="collect($users)->prepend('No linked user', '')->all()" />
                </div>
            </div>

            <x-slot:footer>
                <div class="flex items-center justify-end gap-3">
                    <x-ui.button href="{{ route('salesmen.index') }}" variant="ghost" type="button">Cancel</x-ui.button>
                    <x-ui.button type="submit">Create salesman</x-ui.button>
                </div>
            </x-slot:footer>
        </x-ui.card>
    </form>
@endsection