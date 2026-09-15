<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Field Sales') — {{ config('app.name', 'Field Sales') }}</title>

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

    @include('layouts.partials.sidebar')

    <div class="relative flex min-h-screen flex-1 flex-col"
         :class="$store.shell.collapsed ? 'lg:pl-20' : 'lg:pl-64'">

        @include('layouts.partials.topbar')

        <main class="flex-1 space-y-6 p-6">
            @if (session('status'))
                <x-ui.alert type="success">{{ session('status') }}</x-ui.alert>
            @endif

            @if ($errors->any() && isset($showAllErrors))
                <x-ui.alert type="danger">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-ui.alert>
            @endif

            @yield('content')
        </main>

        <footer class="px-6 pb-6 text-xs text-gray-400 dark:text-gray-600">
            {{ config('app.name', 'Field Sales') }} — Batch 1 · Tenancy & Identity Foundation
        </footer>
    </div>

    @stack('scripts')
</body>
</html>