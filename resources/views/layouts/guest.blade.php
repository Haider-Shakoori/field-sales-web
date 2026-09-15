<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Sign in') — {{ config('app.name', 'Field Sales') }}</title>

    <script>
        (function () {
            const stored = localStorage.getItem('theme');
            const dark = stored === 'dark' || (!stored && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="h-full bg-gray-50 font-sans text-gray-900 antialiased dark:bg-gray-900 dark:text-gray-50">

    <div class="flex min-h-full items-center justify-center px-6 py-12">
        <div class="w-full max-w-md">
            <div class="mb-8 flex flex-col items-center">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-blue-600 font-bold text-white">
                    FS
                </div>
                <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-gray-50">
                    {{ config('app.name', 'Field Sales') }}
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Sign in to manage your sales operation
                </p>
            </div>

            <div class="surface-card p-6">
                @if (session('status'))
                    <x-ui.alert type="success" class="mb-4">{{ session('status') }}</x-ui.alert>
                @endif

                @if ($errors->any())
                    <x-ui.alert type="danger" class="mb-4">
                        <ul class="list-inside list-disc space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </x-ui.alert>
                @endif

                @yield('content')
            </div>

            <p class="mt-6 text-center text-xs text-gray-400 dark:text-gray-600">
                {{ config('app.name', 'Field Sales') }} · Batch 1 — Tenancy & Identity Foundation
            </p>
        </div>
    </div>

    @stack('scripts')
</body>
</html>