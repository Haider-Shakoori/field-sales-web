@php
    $user = auth()->user();
    $tenant = \App\Support\Tenancy\TenantContext::tenant();
@endphp

<header class="sticky top-0 z-30 flex h-16 items-center gap-4 border-b border-gray-200 bg-white/80 px-6 backdrop-blur dark:border-gray-700 dark:bg-gray-800/80">
    <button type="button"
            class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-50"
            x-on:click="$store.shell.collapsed = ! $store.shell.collapsed">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
    </button>

    <div class="min-w-0">
        <p class="truncate text-sm font-semibold text-gray-900 dark:text-gray-50">{{ $tenant?->name ?? 'Platform Console' }}</p>
        <p class="truncate text-xs text-gray-400 dark:text-gray-500">
            @if ($tenant)
                {{ $tenant->timezone }} · {{ $tenant->default_currency }}
            @else
                Super Admin · Platform
            @endif
        </p>
    </div>

    <div class="flex-1"></div>

    <div x-data="themeToggle">
        <button type="button"
                x-on:click="value = value === 'dark' ? 'light' : 'dark'"
                class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-50"
                :title="value === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'">
            <svg x-show="value === 'dark'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2m0 14v2m9-9h-2M5 12H3m13.9-5.9 1.4-1.4M5.7 17.3l1.4 1.4m0-13.4L5.7 6.7m13.6 9.6-1.4 1.4M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
            </svg>
            <svg x-show="value !== 'dark'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8z" />
            </svg>
        </button>
    </div>

    <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false">
        <button type="button"
                x-on:click="open = ! open"
                class="flex items-center gap-2 rounded-lg p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700">
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-sm font-semibold text-white">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </span>
            <svg class="h-4 w-4 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <div x-show="open"
             x-transition
             x-cloak
             class="absolute right-0 z-50 mt-2 w-56 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800">
            <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                <p class="truncate text-sm font-semibold text-gray-900 dark:text-gray-50">{{ $user->name }}</p>
                <p class="truncate text-xs text-gray-400 dark:text-gray-500">{{ $user->email }}</p>
            </div>
            <div class="p-1">
                <a href="{{ route('dashboard') }}" class="block rounded-md px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700">
                    Dashboard
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="block w-full rounded-md px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10">
                        Sign out
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>