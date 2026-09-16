@php
    $user = auth()->user();
    $navSections = [
        'General' => [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'dashboard', 'can' => 'dashboard:view'],
        ],
        'Operations' => [
            ['label' => 'Users', 'route' => 'users.index', 'icon' => 'users', 'can' => 'users:view'],
            ['label' => 'Branches', 'route' => 'branches.index', 'icon' => 'branches', 'can' => 'branches:view'],
            ['label' => 'Salesmen', 'route' => 'salesmen.index', 'icon' => 'salesmen', 'can' => 'salesmen:view'],
            ['label' => 'Supervisors', 'route' => 'supervisors.index', 'icon' => 'users', 'can' => 'supervisors:view'],
            ['label' => 'Devices', 'route' => 'devices.index', 'icon' => 'devices', 'can' => 'devices:view'],
        ],
        'Company' => [
            ['label' => 'Company Settings', 'route' => 'settings.company.edit', 'icon' => 'settings', 'can' => 'settings:view'],
            ['label' => 'Roles & Permissions', 'route' => 'settings.roles.index', 'icon' => 'shield', 'can' => 'roles:view'],
            ['label' => 'Audit Logs', 'route' => 'audit.index', 'icon' => 'clock', 'can' => 'audit:view'],
        ],
        'Catalog' => [
            ['label' => 'Products', 'route' => 'products.index', 'icon' => 'products', 'can' => 'products:view'],
            ['label' => 'Price Lists', 'route' => 'price-lists.index', 'icon' => 'price-lists', 'can' => 'price_lists:view'],
        ],
        'Platform' => [
            ['label' => 'Platform Dashboard', 'route' => 'platform.index', 'icon' => 'platform', 'can' => 'tenants:manage'],
        ],
    ];
@endphp

<aside class="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col border-r border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 lg:flex"
       :class="$store.shell.collapsed ? 'lg:w-20' : 'lg:w-64'">
    <div class="flex h-16 items-center gap-3 border-b border-gray-200 px-6 dark:border-gray-700">
        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-600 font-bold text-white">
            FS
        </div>
        <div x-show="!$store.shell.collapsed" class="min-w-0">
            <p class="truncate text-sm font-semibold text-gray-900 dark:text-gray-50">{{ config('app.name', 'Field Sales') }}</p>
            <p class="truncate text-xs text-gray-400 dark:text-gray-600">
                {{ \App\Support\Tenancy\TenantContext::tenant()?->name ?? 'Platform' }}
            </p>
        </div>
    </div>

    <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-4">
        @foreach ($navSections as $section => $items)
            @php $visible = collect($items)->first(fn ($item) => $item['can'] === null || $user->hasPermission($item['can'])); @endphp

            @if ($visible)
                <div>
                    <p x-show="!$store.shell.collapsed" class="px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-600">
                        {{ $section }}
                    </p>
                    <ul class="space-y-1">
                        @foreach ($items as $item)
                            @if ($item['can'] === null || $user->hasPermission($item['can']))
                                @php
                                    $isActive = request()->routeIs($item['route']);
                                @endphp
                                <li>
                                    <a href="{{ route($item['route']) }}"
                                       class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors {{ $isActive ? 'bg-blue-50 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700/60 dark:hover:text-gray-50' }}"
                                       :title="$store.shell.collapsed ? '{{ $item['label'] }}' : ''">
                                        <x-ui.icon :name="$item['icon']" class="h-5 w-5 shrink-0" />
                                        <span x-show="!$store.shell.collapsed" class="truncate">{{ $item['label'] }}</span>
                                    </a>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                </div>
            @endif
        @endforeach
    </nav>

    <div class="border-t border-gray-200 px-3 py-3 dark:border-gray-700">
        <button type="button"
                x-on:click="$store.shell.collapsed = ! $store.shell.collapsed"
                class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700/60 dark:hover:text-gray-50">
            <x-ui.icon name="panel" class="h-5 w-5 shrink-0" />
            <span x-show="!$store.shell.collapsed" class="truncate">Collapse</span>
        </button>
    </div>
</aside>