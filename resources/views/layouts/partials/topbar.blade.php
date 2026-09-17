@php
    $user = auth()->user();
    $tenant = \App\Support\Tenancy\TenantContext::tenant();
@endphp

<header class="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-3 border-b border-gray-200 bg-white/90 px-4 backdrop-blur sm:px-6 lg:px-8 dark:border-gray-700 dark:bg-gray-900/90">
    {{-- Mobile: open drawer --}}
    <button type="button"
            x-on:click="$store.shell.openMobile()"
            class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500 lg:hidden dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-50"
            aria-label="Open navigation">
        <x-ui.icon name="menu" class="h-5 w-5" />
    </button>

    {{-- Desktop: collapse / expand sidebar --}}
    <button type="button"
            x-on:click="$store.shell.toggleCollapsed()"
            x-bind:aria-expanded="(! $store.shell.collapsed).toString()"
            class="hidden rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500 lg:inline-flex dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-50"
            aria-label="Toggle sidebar">
        <x-ui.icon name="panel" class="h-5 w-5" />
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
                class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-50"
                :aria-label="value === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'"
                :title="value === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'">
            <x-ui.icon x-show="value === 'dark'" name="sun" class="h-5 w-5" />
            <x-ui.icon x-show="value !== 'dark'" name="moon" class="h-5 w-5" />
        </button>
    </div>

    <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
        <button type="button"
                x-on:click="open = ! open"
                x-bind:aria-expanded="open.toString()"
                class="flex items-center gap-2 rounded-lg p-1.5 hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500 dark:hover:bg-gray-800"
                aria-label="Account menu">
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-sm font-semibold text-white">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </span>
            <span class="hidden text-sm font-medium text-gray-700 sm:block dark:text-gray-200">
                {{ $user->name }}
            </span>
            <x-ui.icon name="chevron-down" class="hidden h-4 w-4 text-gray-400 sm:block dark:text-gray-500" />
        </button>

        <div x-show="open"
             x-transition
             x-cloak
             class="absolute end-0 z-50 mt-2 w-56 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800"
             role="menu">
            <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                <p class="truncate text-sm font-semibold text-gray-900 dark:text-gray-50">{{ $user->name }}</p>
                <p class="truncate text-xs text-gray-400 dark:text-gray-500">{{ $user->email }}</p>
            </div>
            <div class="p-1">
                <a href="{{ route('dashboard') }}"
                   role="menuitem"
                   class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500 dark:text-gray-200 dark:hover:bg-gray-700">
                    <x-ui.icon name="dashboard" class="h-4 w-4" />
                    Dashboard
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            role="menuitem"
                            class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-500 dark:text-red-400 dark:hover:bg-red-500/10">
                        <x-ui.icon name="logout" class="h-4 w-4" />
                        Sign out
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
