<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), ['fa', 'ps'], true) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="color-scheme" content="dark">
    <title>{{ $title ?? 'Field Sales' }}</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='8' fill='%236366f1'/><text x='16' y='22' font-family='Arial' font-size='16' font-weight='700' fill='white' text-anchor='middle'>F</text></svg>">
    @php
        $cssPath = public_path('css/app.css');
        $cssVersion = is_file($cssPath) ? filemtime($cssPath) : '1';
    @endphp
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ $cssVersion }}">
    <style>
        .fp-sidebar {
            left: 0;
            right: auto;
            transform: translateX(-100%);
        }

        html[dir="rtl"] .fp-sidebar {
            left: auto;
            right: 0;
            transform: translateX(100%);
        }

        #sidebar-toggle:checked ~ .fp-sidebar {
            transform: translateX(0);
        }

        @media (min-width: 1024px) {
            .fp-sidebar {
                transform: translateX(0) !important;
            }

            .fp-shell-auth {
                padding-left: 18rem;
                padding-right: 0;
            }

            html[dir="rtl"] .fp-shell-auth {
                padding-left: 0;
                padding-right: 18rem;
            }
        }
    </style>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
@php
    $webGuardName = auth()->guard()->getName();
    $context = app(\App\Tenancy\TenantContext::class);
    $currentUser = auth()->user();
    $isPlatformContext = $context->isPlatform();
    $tenantName = $currentUser?->tenant?->name;
    $rtl = in_array(app()->getLocale(), ['fa', 'ps'], true);
    $initials = $currentUser
        ? collect(explode(' ', trim($currentUser->name)))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('')
        : '';
@endphp

@if($currentUser)
    @php
        $navGroups = [
            [
                'label' => 'Overview',
                'links' => [
                    ['route' => 'admin.dashboard', 'match' => 'admin.dashboard', 'label' => 'Dashboard', 'icon' => 'home', 'can' => 'reports:view'],
                    ['route' => 'admin.live-map', 'match' => 'admin.live-map', 'label' => 'Live map', 'icon' => 'map-pin', 'can' => 'tracking:view'],
                ],
            ],
            ...($currentUser->isPlatformAdmin() ? [[
                'label' => 'Platform',
                'links' => [
                    ['route' => 'admin.organizations.index', 'match' => 'admin.organizations.*', 'label' => 'Organizations', 'icon' => 'building', 'can' => null],
                ],
            ]] : []),
            [
                'label' => 'Team',
                'links' => [
                    ['route' => 'admin.users.index', 'match' => 'admin.users.*', 'label' => 'Users', 'icon' => 'users', 'can' => 'users:view'],
                    ['route' => 'admin.roles.index', 'match' => 'admin.roles.*', 'label' => 'Roles', 'icon' => 'shield', 'can' => 'roles:view'],
                    ['route' => 'admin.branches.index', 'match' => 'admin.branches.*', 'label' => 'Branches', 'icon' => 'building', 'can' => 'branches:view'],
                    ['route' => 'admin.salesmen.index', 'match' => 'admin.salesmen.*', 'label' => 'Salesmen', 'icon' => 'user', 'can' => 'sales-team:view'],
                    ['route' => 'admin.attendance.index', 'match' => 'admin.attendance.*', 'label' => 'Attendance', 'icon' => 'calendar-check', 'can' => 'sales-team:view'],
                    ['route' => 'admin.supervisors.index', 'match' => 'admin.supervisors.*', 'label' => 'Supervisors', 'icon' => 'user-plus', 'can' => 'sales-team:view'],
                    ['route' => 'admin.devices.index', 'match' => 'admin.devices.*', 'label' => 'Devices', 'icon' => 'device', 'can' => 'sales-team:view'],
                    ['route' => 'admin.salesman-assignments.index', 'match' => 'admin.salesman-assignments.*', 'label' => 'Salesman assignments', 'icon' => 'clipboard', 'can' => 'sales-team:view'],
                    ['route' => 'admin.supervisor-assignments.index', 'match' => 'admin.supervisor-assignments.*', 'label' => 'Supervisor assignments', 'icon' => 'clipboard', 'can' => 'sales-team:view'],
                ],
            ],
            [
                'label' => 'CRM',
                'links' => [
                    ['route' => 'admin.customers.index', 'match' => 'admin.customers.*', 'label' => 'Customers', 'icon' => 'briefcase', 'can' => 'customers:view'],
                    ['route' => 'admin.territories.index', 'match' => 'admin.territories.*', 'label' => 'Territories', 'icon' => 'map', 'can' => 'customers:view'],
                    ['route' => 'admin.routes.index', 'match' => 'admin.routes.*', 'label' => 'Routes', 'icon' => 'map-pin', 'can' => 'customers:view'],
                    ['route' => 'admin.daily-planner.index', 'match' => 'admin.daily-planner.*', 'label' => 'Daily planner', 'icon' => 'calendar-check', 'can' => 'sales-team:view'],
                    ['route' => 'admin.call-activities.index', 'match' => 'admin.call-activities.*', 'label' => 'Calls', 'icon' => 'phone', 'can' => 'customers:view'],
                    ['route' => 'admin.follow-ups.index', 'match' => 'admin.follow-ups.*', 'label' => 'Follow-ups', 'icon' => 'calendar-check', 'can' => 'customers:view'],
                ],
            ],
            [
                'label' => 'Field operations',
                'links' => [
                    ['route' => 'admin.visits.index', 'match' => 'admin.visits.*', 'label' => 'Visits', 'icon' => 'calendar-check', 'can' => 'visits:view'],
                    ['route' => 'admin.visit-forms.index', 'match' => 'admin.visit-forms.*', 'label' => 'Visit forms', 'icon' => 'clipboard', 'can' => 'visits:view'],
                    ['route' => 'admin.orders.index', 'match' => 'admin.orders.*', 'label' => 'Orders', 'icon' => 'cart', 'can' => 'orders:view'],
                    ['route' => 'admin.stock.index', 'match' => 'admin.stock.*', 'label' => 'Salesman stock', 'icon' => 'cube', 'can' => 'stock:view'],
                    ['route' => 'admin.returns.index', 'match' => 'admin.returns.*', 'label' => 'Returns', 'icon' => 'rotate-ccw', 'can' => 'returns:view'],
                    ['route' => 'admin.collections.index', 'match' => 'admin.collections.*', 'label' => 'Collections', 'icon' => 'banknotes', 'can' => 'collections:view'],
                    ['route' => 'admin.expenses.index', 'match' => 'admin.expenses.*', 'label' => 'Expenses', 'icon' => 'receipt', 'can' => 'expenses:view'],
                    ['route' => 'admin.targets.index', 'match' => 'admin.targets.*', 'label' => 'Targets', 'icon' => 'flag', 'can' => 'targets:view'],
                ],
            ],
            [
                'label' => 'Catalog',
                'links' => [
                    ['route' => 'admin.products.index', 'match' => 'admin.products.*', 'label' => 'Products', 'icon' => 'cube', 'can' => 'catalog:view'],
                    ['route' => 'admin.price-lists.index', 'match' => 'admin.price-lists.*', 'label' => 'Price lists', 'icon' => 'tag', 'can' => 'catalog:view'],
                ],
            ],
            [
                'label' => 'Insights',
                'links' => [
                    ['route' => 'admin.alerts.index', 'match' => 'admin.alerts.*', 'label' => 'Alerts', 'icon' => 'alert', 'can' => 'reports:view'],
                    ['route' => 'admin.reports.index', 'match' => 'admin.reports.*', 'label' => 'Reports', 'icon' => 'chart', 'can' => 'reports:view'],
                    ['route' => 'admin.scorecards.index', 'match' => 'admin.scorecards.*', 'label' => 'Scorecards', 'icon' => 'chart', 'can' => 'reports:view'],
                    ['route' => 'admin.notifications.index', 'match' => 'admin.notifications.*', 'label' => 'Notifications', 'icon' => 'bell', 'can' => null],
                    ['route' => 'admin.audit.index', 'match' => 'admin.audit.*', 'label' => 'Audit', 'icon' => 'document-search', 'can' => 'audit:view'],
                ],
            ],
            [
                'label' => 'Settings',
                'links' => [
                    ['route' => 'organization.edit', 'match' => 'organization.*', 'label' => 'Organization', 'icon' => 'building', 'can' => 'settings:view'],
                    ['route' => 'tracking.edit', 'match' => 'tracking.*', 'label' => 'Tracking settings', 'icon' => 'cog', 'can' => 'settings:view'],
                ],
            ],
        ];
    @endphp

    <input id="sidebar-toggle" type="checkbox" class="peer sr-only">

    <header class="sticky top-0 z-30 flex items-center justify-between border-b border-white/10 bg-slate-950/80 px-4 py-3 backdrop-blur lg:hidden">
        <div class="flex items-center gap-3">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-sky-400 text-base font-black text-white">F</span>
            <div>
                <p class="text-base font-bold leading-tight">Field Sales</p>
                <p class="truncate text-xs text-slate-400">{{ $isPlatformContext ? __('Platform console') : ($tenantName ?? __('Operations Console')) }}</p>
            </div>
        </div>
        <label for="sidebar-toggle" class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-white/10 px-3 py-2 text-sm font-medium hover:bg-white/20">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
            {{ __('Menu') }}
        </label>
    </header>

    <label for="sidebar-toggle" aria-hidden="true" class="fixed inset-0 z-30 hidden bg-slate-950/70 backdrop-blur-sm peer-checked:block lg:hidden"></label>

    <aside class="fp-sidebar fixed inset-y-0 z-40 flex w-72 flex-col border-white/10 bg-slate-900/70 backdrop-blur-xl transition-transform duration-200">
        <div class="fp-sidebar-brand flex items-center gap-3 border-b border-white/10 px-5 py-5">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-sky-400 text-lg font-black text-white shadow-lg shadow-indigo-500/30">F</span>
            <div class="min-w-0">
                <p class="text-base font-bold leading-tight">Field Sales</p>
                <p class="truncate text-xs text-slate-400">{{ $isPlatformContext ? __('Platform console') : ($tenantName ?? __('Operations Console')) }}</p>
            </div>
        </div>

        <nav class="fp-sidebar-nav flex-1 space-y-5 overflow-y-auto px-3 py-4 text-sm">
            @foreach($navGroups as $group)
                @php
                    $groupVisible = collect($group['links'])->contains(
                        fn (array $link) => $link['can'] === null || $currentUser->hasPermission($link['can'])
                    );
                @endphp

                @if($groupVisible)
                    <div>
                        <p class="fp-sidebar-group-label px-3 pb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ __($group['label']) }}</p>
                        <div class="space-y-1">
                            @foreach($group['links'] as $link)
                                @if($link['can'] === null || $currentUser->hasPermission($link['can']))
                                    <x-nav-link :href="route($link['route'])" :icon="$link['icon']" :active="request()->routeIs($link['match'])">{{ __($link['label']) }}</x-nav-link>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </nav>

        @if(session()->has($webGuardName))
            <div class="fp-sidebar-footer border-t border-white/10 p-3">
                <div class="mb-2 flex items-center gap-3 rounded-xl bg-white/5 px-3 py-2.5">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-500/20 text-sm font-semibold text-indigo-200">{{ $initials ?: '?' }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ $currentUser->name }}</p>
                        <p class="truncate text-xs text-slate-400">{{ str($currentUser->role)->replace('_', ' ')->title() }}</p>
                    </div>
                    @if($currentUser->isPlatformAdmin())
                        <span class="shrink-0 rounded-full bg-indigo-500/20 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-indigo-200">Platform</span>
                    @endif
                </div>
                <form method="POST" action="{{ route('locale.update') }}" class="fp-language-form mb-2">
                    @csrf
                    <select name="locale" aria-label="{{ __('Language') }}" class="min-w-0 rounded-xl border border-white/10 bg-slate-950 px-3 py-2 text-sm">
                        <option value="en" @selected(app()->getLocale() === 'en')>{{ __('English') }}</option>
                        <option value="fa" @selected(app()->getLocale() === 'fa')>{{ __('Dari') }}</option>
                        <option value="ps" @selected(app()->getLocale() === 'ps')>{{ __('Pashto') }}</option>
                    </select>
                    <button class="rounded-xl bg-white/10 px-3 py-2 text-xs font-semibold hover:bg-white/20">{{ __('Apply') }}</button>
                </form>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="flex w-full items-center justify-center gap-2 rounded-xl bg-white/10 px-3 py-2 text-sm font-medium hover:bg-white/20">
                        <svg class="fp-directional-icon h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="m16 17 5-5-5-5"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12H9"/>
                        </svg>
                        {{ __('Sign out') }}
                    </button>
                </form>
            </div>
        @endif
    </aside>
@endif

<div class="{{ $currentUser ? 'fp-shell-auth' : '' }}">
    @unless($currentUser)
        <nav class="border-b border-white/10 bg-slate-900/80 backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center gap-4 px-6 py-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-sky-400 text-lg font-black text-white shadow-lg shadow-indigo-500/30">F</span>
                    <div>
                        <p class="text-lg font-bold leading-tight">Field Sales</p>
                        <p class="text-xs text-slate-400">{{ __('Operations Console') }}</p>
                    </div>
                </div>
            </div>
        </nav>
    @endunless

    <main class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">
        @if(session('status'))
            <div class="mb-5 flex items-start gap-3 rounded-xl border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 text-emerald-200">
                <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                    <circle cx="12" cy="12" r="9"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.5 12.5 2.5 2.5 4.5-5"/>
                </svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if($errors->any())
            <div class="mb-5 flex items-start gap-3 rounded-xl border border-red-400/20 bg-red-500/10 px-4 py-3 text-red-200">
                <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                    <circle cx="12" cy="12" r="9"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4M12 16h.01"/>
                </svg>
                <ul class="list-disc space-y-1 pl-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{ $slot }}
    </main>
</div>
</body>
</html>
