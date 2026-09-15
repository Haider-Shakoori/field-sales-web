@extends('layouts.guest')

@section('title', 'Sign in')

@section('content')
    <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
        @csrf

        <x-ui.input name="email" type="email" label="Email address" :value="old('email')" required autofocus autocomplete="email" placeholder="you@company.com" />

        <x-ui.input name="password" type="password" label="Password" required autocomplete="current-password" placeholder="••••••••" />

        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-900">
                Remember me
            </label>

            <a href="{{ route('password.request') }}" class="text-sm font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400">
                Forgot password?
            </a>
        </div>

        <x-ui.button type="submit" class="w-full" variant="primary">
            <x-ui.icon name="user" class="h-4 w-4" />
            Sign in
        </x-ui.button>
    </form>

    <div class="mt-6 rounded-lg border border-dashed border-gray-200 bg-gray-50 px-4 py-3 text-xs text-gray-500 dark:border-gray-700 dark:bg-gray-800/60 dark:text-gray-400">
        <p class="mb-1 font-semibold text-gray-600 dark:text-gray-300">Demo accounts (all password: <code>Password123!</code>)</p>
        <p>owner@demo.test · admin@demo.test · manager@demo.test · salesman@demo.test</p>
        <p class="mt-1 text-gray-400 dark:text-gray-600">Platform: superadmin@platform.test · Tenant B: owner@fresh.test</p>
    </div>
@endsection