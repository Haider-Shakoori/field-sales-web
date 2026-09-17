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

            if (localStorage.getItem('sidebar-collapsed') === '1') {
                document.documentElement.classList.add('sidebar-collapsed');
            }
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="h-full bg-gray-50 font-sans text-gray-900 antialiased dark:bg-gray-900 dark:text-gray-50">

    <a href="#main-content"
       class="sr-only focus:not-sr-only focus:absolute focus:start-4 focus:top-4 focus:z-[60] focus:rounded-lg focus:bg-blue-600 focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white">
        Skip to content
    </a>

    <div class="flex min-h-screen">
        @include('layouts.partials.sidebar')

        <div class="flex min-h-screen min-w-0 flex-1 flex-col">
            @include('layouts.partials.topbar')

            <main id="main-content" class="flex-1 space-y-6 px-4 py-5 sm:px-6 sm:py-6 lg:px-8">
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

            <footer class="px-4 pb-6 text-xs text-gray-400 sm:px-6 lg:px-8 dark:text-gray-600">
                &copy; {{ now()->year }} {{ config('app.name', 'Field Sales') }}
            </footer>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
