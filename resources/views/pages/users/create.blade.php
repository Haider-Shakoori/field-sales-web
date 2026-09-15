@extends('layouts.app')

@section('title', 'Add User')

@section('content')
    <x-ui.page-header title="Add user"
                      description="Create a member and assign a company role." />

    <form method="POST" action="{{ route('users.store') }}" class="max-w-2xl space-y-6">
        @csrf

        <x-ui.card title="Member details">
            <div class="space-y-5">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-ui.input name="name" label="Full name" :value="old('name')" required autofocus placeholder="e.g. Hamid Karzai" />
                    <x-ui.input name="phone" label="Phone" :value="old('phone')" placeholder="+93 ..." />
                </div>

                <x-ui.input name="email" type="email" label="Email address" :value="old('email')" required placeholder="member@company.com" />

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-ui.input name="password" type="password" label="Password" required autocomplete="new-password" placeholder="••••••••" />
                    <x-ui.input name="password_confirmation" type="password" label="Confirm password" required autocomplete="new-password" placeholder="••••••••" />
                </div>

                <x-ui.select name="role"
                             label="Role"
                             :value="old('role')"
                             :options="$roles"
                             required />
            </div>

            <x-slot:footer>
                <div class="flex items-center justify-end gap-3">
                    <x-ui.button href="{{ route('users.index') }}" variant="ghost" type="button">Cancel</x-ui.button>
                    <x-ui.button type="submit">Create user</x-ui.button>
                </div>
            </x-slot:footer>
        </x-ui.card>
    </form>
@endsection