@extends('layouts.guest')

@section('title', 'Reset password')

@section('content')
    <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-ui.input name="email" type="email" label="Email address" :value="old('email', $request->email)" required autofocus autocomplete="email" placeholder="you@company.com" />

        <x-ui.input name="password" type="password" label="New password" required autocomplete="new-password" placeholder="••••••••" />

        <x-ui.input name="password_confirmation" type="password" label="Confirm new password" required autocomplete="new-password" placeholder="••••••••" />

        <x-ui.button type="submit" class="w-full" variant="primary">
            Reset password
        </x-ui.button>
    </form>
@endsection