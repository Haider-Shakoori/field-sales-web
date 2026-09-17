@php
    $user = auth()->user();
    $tenant = \App\Support\Tenancy\TenantContext::tenant();
    $navSections = [
        'General' => [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'dashboard', 'can' => 'dashboard:view'],
        ],
        'Field operations' => [
            ['label' => 'Routes', 'route' => 'routes.index', 'icon' => 'map', 'can' => 'routes:view'],
            ['label' => 'Salesmen', 'route' => 'salesmen.index', 'icon' => 'salesmen', 'can' => 'salesmen:view'],
            ['label' => 'Supervisors', 'route' => 'supervisors.index', 'icon' => 'users', 'can' => 'supervisors:view'],
            ['label' => 'Devices', 'route' => 'devices.index', 'icon' => 'devices', 'can' => 'devices:view'],
        ],
        'Administration' => [
            ['label' => 'Users', 'route' => 'users.index', 'icon' => 'user', 'can' => 'users:view'],
            ['label' => 'Branches', 'route' => 'branches.index', 'icon' => 'building', 'can' => 'branches:view'],
        ],
        'Catalog' => [
            ['label' => 'Products', 'route' => 'products.index', 'icon' => 'products', 'can' => 'products:view'],
            ['label' => 'Price Lists', 'route' => 'price-lists.index', 'icon' => 'price-lists', 'can' => 'price_lists:view'],
        ],
        'Company' => [
            ['label' => 'Company Settings', 'route' => 'settings.company.edit', 'icon' => 'settings', 'can' => 'settings:view'],
            ['label' => 'Roles & Permissions', 'route' => 'settings.roles.index', 'icon' => 'shield', 'can' => 'roles:view'],
            ['label' => 'Audit Logs', 'route' => 'audit.index', 'icon' => 'clock', 'can' => 'audit:view'],
        ],
        'Platform' => [
            ['label' => 'Platform Dashboard', 'route' => 'platform.index', 'icon' => 'platform', 'can' => 'tenants:manage'],
        ],
    ];
@endphp

{{-- Mobile backdrop --}}
<div x-cloak
     x-show="$store.shell.mobileOpen"
     x-transition.opacity
     x-on:click="$store.shell.closeMobile()"
     class="fixed inset-0 z-40 bg-gray-950/60 backdrop-blur-sm lg:hidden"
     aria-hidden="true"></div>

<aside x-on:keydown.escape.window="$store.shell.closeMobile()"
       x-on:resize.window.throttle.200ms="if (window.innerWidth >= 1024) $store.shell.closeMobile()"
       x-effect="$store.shell.mobileOpen ? document.body.classList.add('overflow-hidden') : document.body.classList.remove('overflow-hidden')"
       class="app-sidebar fixed inset-y-0 start-0 z-50 flex flex-col border-e border-slate-800 bg-slate-900 lg:sticky lg:top-0 lg:z-40 lg:h-screen">

    {{-- Brand --}}
    <div class="flex h-16 shrink-0 items-center gap-3 border-b border-slate-800 px-4 lg:px-5">
        <a href="{{ route('dashboard') }}"
           class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-600 text-sm font-bold text-white shadow-sm"
           aria-label="{{ config('app.name', 'Field Sales') }}">
            FS
        </a>
        <div class="sidebar-collapsible min-w-0 flex-1">
            <p class="truncate text-sm font-semibold text-white">{{ config('app.name', 'Field Sales') }}</p>
            <p class="truncate text-xs text-slate-400">{{ $tenant?->name ?? 'Admin Panel' }}</p>
        </div>
        <button type="button"
                x-on:click="$store.shell.closeMobile()"
                class="rounded-lg p-2 text-slate-400 hover:bg-slate-800 hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500 lg:hidden"
                aria-label="Close navigation">
            <x-ui.icon name="x" class="h-5 w-5" />
        </button>
    </div>

    {{-- Navigation --}}
    <nav class="sidebar-scroll flex-1 space-y-6 overflow-y-auto px-3 py-4" aria-label="Primary">
        @foreach ($navSections as $section => $items)
            @php $visible = collect($items)->contains(fn ($item) => $item['can'] === null || $user->hasPermission($item['can'])); @endphp

            @if ($visible)
                <div>
                    <p class="sidebar-collapsible px-3 pb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                        {{ $section }}
                    </p>
                    <ul class="space-y-1">
                        @foreach ($items as $item)
                            @if ($item['can'] === null || $user->hasPermission($item['can']))
                                @php $isActive = request()->routeIs($item['route']); @endphp
                                <li>
                                    <a href="{{ route($item['route']) }}"
                                       x-on:click="$store.shell.closeMobile()"
                                       x-bind:title="$store.shell.collapsed ? @js($item['label']) : null"
                                       @if ($isActive) aria-current="page" @endif
                                       class="sidebar-nav-item group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500 {{ $isActive ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                                        <x-ui.icon :name="$item['icon']" class="h-5 w-5 shrink-0" />
                                        <span class="sidebar-collapsible truncate">{{ $item['label'] }}</span>
                                    </a>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                </div>
            @endif
        @endforeach
    </nav>

    {{-- Profile --}}
    <div class="shrink-0 border-t border-slate-800 p-3">
        <div class="sidebar-nav-item flex items-center gap-3 rounded-lg px-2 py-2"
             x-bind:title="$store.shell.collapsed ? @js($user->name) : null">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-500/20 text-sm font-semibold text-blue-300 ring-1 ring-inset ring-blue-400/30">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </span>
            <div class="sidebar-collapsible min-w-0">
                <p class="truncate text-sm font-semibold text-white">{{ $user->name }}</p>
                <p class="truncate text-xs text-slate-400">{{ ucfirst(str_replace('_', ' ', $user->role)) }}</p>
            </div>
        </div>
    </div>
</aside>
