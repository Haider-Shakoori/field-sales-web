@extends('layouts.guest')

@section('title', 'Forgot password')

@section('content')
    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf

        <x-ui.input name="email" type="email" label="Email address" :value="old('email')" required autofocus autocomplete="email" placeholder="you@company.com" />

        <x-ui.button type="submit" class="w-full" variant="primary">
            <x-ui.icon name="mail" class="h-4 w-4" />
            Email password reset link
        </x-ui.button>
    </form>

    <p class="mt-6 text-center text-sm">
        <a href="{{ route('login') }}" class="font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400">
            Back to sign in
        </a>
    </p>
@endsection