<!doctype html>
<html lang="en" data-theme="dark" data-sidebar-collapsed="false">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#031733">
    <title>{{ $title ?? 'FieldPulse' }}</title>

    <script>
        (() => {
            try {
                const theme = localStorage.getItem('fieldpulse-theme') || 'dark';
                const collapsed = localStorage.getItem('fieldpulse-sidebar-collapsed') === 'true';
                document.documentElement.dataset.theme = theme;
                document.documentElement.dataset.sidebarCollapsed = collapsed ? 'true' : 'false';
            } catch (_) {
                document.documentElement.dataset.theme = 'dark';
            }
        })();
    </script>

    <link rel="preconnect" href="https://cdn.tailwindcss.com">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preconnect" href="https://unpkg.com" crossorigin>
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        :root {
            color-scheme: dark;
            --fp-page-bg: #061325;
            --fp-surface: #0b1d34;
            --fp-surface-elevated: #102640;
            --fp-border: rgba(148, 163, 184, .14);
            --fp-text: #e7f4ff;
            --fp-muted: #8da3b9;
            --fp-accent: #20c9ff;
            --fp-accent-strong: #3188ff;
        }

        html[data-theme="light"] {
            color-scheme: light;
            --fp-page-bg: #f4f8fc;
            --fp-surface: #ffffff;
            --fp-surface-elevated: #f8fbff;
            --fp-border: #dce6f0;
            --fp-text: #102238;
            --fp-muted: #60758a;
        }

        html, body {
            min-height: 100%;
            background: var(--fp-page-bg);
            color: var(--fp-text);
        }

        body {
            margin: 0;
            text-rendering: optimizeLegibility;
            -webkit-font-smoothing: antialiased;
        }

        .fp-sidebar {
            width: 18rem;
            transform: translateX(-100%);
            background:
                radial-gradient(circle at 12% 0%, rgba(32, 201, 255, .16), transparent 28rem),
                linear-gradient(180deg, #031733 0%, #061a31 55%, #041326 100%);
            box-shadow: 22px 0 60px rgba(0, 0, 0, .18);
        }

        .fp-sidebar-overlay {
            opacity: 0;
            pointer-events: none;
        }

        html[data-sidebar-open="true"] .fp-sidebar {
            transform: translateX(0);
        }

        html[data-sidebar-open="true"] .fp-sidebar-overlay {
            opacity: 1;
            pointer-events: auto;
        }

        .fp-main-shell {
            min-height: 100vh;
            transition: padding-left .22s ease;
        }

        .fp-topbar {
            background: color-mix(in srgb, var(--fp-page-bg) 88%, transparent);
            border-color: var(--fp-border);
        }

        .fp-content {
            color: var(--fp-text);
        }

        .fp-content > * {
            animation: fp-enter .22s ease-out both;
        }

        @keyframes fp-enter {
            from { opacity: .55; transform: translateY(4px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                scroll-behavior: auto !important;
                transition-duration: 0.01ms !important;
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
            }
        }

        @media (min-width: 1024px) {
            .fp-sidebar {
                transform: none;
            }

            .fp-main-shell {
                padding-left: 18rem;
            }

            html[data-sidebar-collapsed="true"] .fp-sidebar {
                width: 5.5rem;
            }

            html[data-sidebar-collapsed="true"] .fp-main-shell {
                padding-left: 5.5rem;
            }

            html[data-sidebar-collapsed="true"] .fp-sidebar-copy,
            html[data-sidebar-collapsed="true"] .fp-sidebar-group-title,
            html[data-sidebar-collapsed="true"] .fp-sidebar-user-copy,
            html[data-sidebar-collapsed="true"] .fp-nav-label {
                display: none;
            }

            html[data-sidebar-collapsed="true"] .fp-sidebar-brand,
            html[data-sidebar-collapsed="true"] .fp-nav-link,
            html[data-sidebar-collapsed="true"] .fp-sidebar-footer {
                justify-content: center;
            }

            html[data-sidebar-collapsed="true"] .fp-nav-link {
                padding-left: .75rem;
                padding-right: .75rem;
            }
        }

        html[data-theme="light"] .fp-content .bg-slate-950 {
            background-color: #f8fbff !important;
        }

        html[data-theme="light"] .fp-content .bg-slate-900,
        html[data-theme="light"] .fp-content [class~="bg-slate-900/80"],
        html[data-theme="light"] .fp-content [class~="bg-slate-900/90"] {
            background-color: #ffffff !important;
        }

        html[data-theme="light"] .fp-content .bg-slate-800,
        html[data-theme="light"] .fp-content [class~="bg-white/5"],
        html[data-theme="light"] .fp-content [class~="bg-white/10"] {
            background-color: #f2f6fa !important;
        }

        html[data-theme="light"] .fp-content [class~="border-white/10"],
        html[data-theme="light"] .fp-content [class~="border-white/5"] {
            border-color: #dce6f0 !important;
        }

        html[data-theme="light"] .fp-content .text-slate-100,
        html[data-theme="light"] .fp-content .text-white {
            color: #102238 !important;
        }

        html[data-theme="light"] .fp-content .text-slate-200,
        html[data-theme="light"] .fp-content .text-slate-300 {
            color: #30485f !important;
        }

        html[data-theme="light"] .fp-content .text-slate-400 {
            color: #60758a !important;
        }

        html[data-theme="light"] .fp-content .text-slate-500 {
            color: #7890a5 !important;
        }

        .fp-panel-performance {
            content-visibility: auto;
            contain-intrinsic-size: 420px;
        }
    </style>
</head>
<body>
@php
    $webGuardName = auth()->guard()->getName();
    $tenantReady = app(\App\Tenancy\TenantContext::class)->hasTenant();
    $currentUser = $tenantReady ? auth()->user() : null;
    $permissionSlugs = $currentUser
        ? $currentUser->roles()
            ->with('permissions:id,slug')
            ->get()
            ->flatMap(fn ($role) => $role->permissions->pluck('slug'))
            ->unique()
            ->flip()
        : collect();

    $navigationGroups = [
        [
            'label' => 'Overview',
            'items' => [
                ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'match' => 'admin.dashboard*', 'permission' => 'reports:view', 'short' => 'DB'],
                ['label' => 'Notifications', 'route' => 'admin.notifications.index', 'match' => 'admin.notifications.*', 'permission' => null, 'short' => 'NT'],
                ['label' => 'Alerts', 'route' => 'admin.alerts.index', 'match' => 'admin.alerts.*', 'permission' => 'reports:view', 'short' => 'AL'],
                ['label' => 'Reports', 'route' => 'admin.reports.index', 'match' => 'admin.reports.*', 'permission' => 'reports:view', 'short' => 'RP'],
            ],
        ],
        [
            'label' => 'People & access',
            'items' => [
                ['label' => 'Users', 'route' => 'admin.users.index', 'match' => 'admin.users.*', 'permission' => 'users:view', 'short' => 'US'],
                ['label' => 'Roles', 'route' => 'admin.roles.index', 'match' => 'admin.roles.*', 'permission' => 'roles:view', 'short' => 'RL'],
                ['label' => 'Branches', 'route' => 'admin.branches.index', 'match' => 'admin.branches.*', 'permission' => 'branches:view', 'short' => 'BR'],
                ['label' => 'Salesmen', 'route' => 'admin.salesmen.index', 'match' => 'admin.salesmen.*', 'permission' => 'sales-team:view', 'short' => 'SM'],
                ['label' => 'Supervisors', 'route' => 'admin.supervisors.index', 'match' => 'admin.supervisors.*', 'permission' => 'sales-team:view', 'short' => 'SV'],
                ['label' => 'Devices', 'route' => 'admin.devices.index', 'match' => 'admin.devices.*', 'permission' => 'sales-team:view', 'short' => 'DV'],
                ['label' => 'Salesman assignments', 'route' => 'admin.salesman-assignments.index', 'match' => 'admin.salesman-assignments.*', 'permission' => 'sales-team:view', 'short' => 'SA'],
                ['label' => 'Supervisor assignments', 'route' => 'admin.supervisor-assignments.index', 'match' => 'admin.supervisor-assignments.*', 'permission' => 'sales-team:view', 'short' => 'SU'],
            ],
        ],
        [
            'label' => 'Field operations',
            'items' => [
                ['label' => 'Customers', 'route' => 'admin.customers.index', 'match' => 'admin.customers.*', 'permission' => 'customers:view', 'short' => 'CU'],
                ['label' => 'Territories', 'route' => 'admin.territories.index', 'match' => 'admin.territories.*', 'permission' => 'customers:view', 'short' => 'TR'],
                ['label' => 'Routes', 'route' => 'admin.routes.index', 'match' => 'admin.routes.*', 'permission' => 'customers:view', 'short' => 'RT'],
                ['label' => 'Calls', 'route' => 'admin.call-activities.index', 'match' => 'admin.call-activities.*', 'permission' => 'customers:view', 'short' => 'CL'],
                ['label' => 'Visits', 'route' => 'admin.visits.index', 'match' => 'admin.visits.*', 'permission' => 'visits:view', 'short' => 'VS'],
                ['label' => 'Orders', 'route' => 'admin.orders.index', 'match' => 'admin.orders.*', 'permission' => 'orders:view', 'short' => 'OR'],
                ['label' => 'Collections', 'route' => 'admin.collections.index', 'match' => 'admin.collections.*', 'permission' => 'collections:view', 'short' => 'CO'],
                ['label' => 'Expenses', 'route' => 'admin.expenses.index', 'match' => 'admin.expenses.*', 'permission' => 'expenses:view', 'short' => 'EX'],
                ['label' => 'Targets', 'route' => 'admin.targets.index', 'match' => 'admin.targets.*', 'permission' => 'targets:view', 'short' => 'TG'],
            ],
        ],
        [
            'label' => 'Catalog & settings',
            'items' => [
                ['label' => 'Products', 'route' => 'admin.products.index', 'match' => 'admin.products.*', 'permission' => 'catalog:view', 'short' => 'PR'],
                ['label' => 'Price lists', 'route' => 'admin.price-lists.index', 'match' => 'admin.price-lists.*', 'permission' => 'catalog:view', 'short' => 'PL'],
                ['label' => 'Audit log', 'route' => 'admin.audit.index', 'match' => 'admin.audit.*', 'permission' => 'audit:view', 'short' => 'AU'],
                ['label' => 'Tracking settings', 'route' => 'tracking.edit', 'match' => 'tracking.*', 'permission' => 'settings:view', 'short' => 'TS'],
            ],
        ],
    ];
@endphp

@if($currentUser)
    <div data-fieldpulse-shell>
        <div
            class="fp-sidebar-overlay fixed inset-0 z-40 bg-slate-950/70 backdrop-blur-sm transition-opacity lg:hidden"
            data-sidebar-overlay
            aria-hidden="true"
        ></div>

        <aside
            class="fp-sidebar fixed inset-y-0 left-0 z-50 flex flex-col overflow-hidden border-r border-cyan-200/10 text-slate-100 transition-[width,transform] duration-200"
            data-sidebar
            aria-label="Primary navigation"
        >
            <div class="fp-sidebar-brand flex h-20 shrink-0 items-center gap-3 border-b border-white/10 px-4">
                <div class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-cyan-300/10 ring-1 ring-inset ring-cyan-200/20 shadow-lg shadow-cyan-950/30">
                    <svg viewBox="0 0 56 56" class="h-8 w-8" aria-hidden="true">
                        <defs>
                            <linearGradient id="fieldpulse-mark" x1="8" y1="7" x2="47" y2="48" gradientUnits="userSpaceOnUse">
                                <stop stop-color="#2E7CFF"/>
                                <stop offset="1" stop-color="#26D8FF"/>
                            </linearGradient>
                        </defs>
                        <path d="M28 5.5c-10.7 0-19.4 8.7-19.4 19.4 0 13.1 15.2 24 18.2 26.1.7.5 1.7.5 2.4 0 3-2.1 18.2-13 18.2-26.1C47.4 14.2 38.7 5.5 28 5.5Z" fill="none" stroke="url(#fieldpulse-mark)" stroke-width="4.2"/>
                        <path d="M17 35.4c5.1-1.8 9.1-4.5 12.1-8.1 3.6-4.2 6-8.7 10.4-11.8" fill="none" stroke="url(#fieldpulse-mark)" stroke-width="3.4" stroke-linecap="round"/>
                        <rect x="20.5" y="25.8" width="4.2" height="8.7" rx="2.1" fill="#27D7FF"/>
                        <rect x="27.1" y="21.3" width="4.2" height="10.2" rx="2.1" fill="#27D7FF"/>
                        <rect x="33.7" y="16.8" width="4.2" height="11.7" rx="2.1" fill="#27D7FF"/>
                    </svg>
                </div>
                <div class="fp-sidebar-copy min-w-0">
                    <div class="truncate text-lg font-extrabold tracking-tight">
                        <span class="text-blue-400">Field</span><span class="text-cyan-300">Pulse</span>
                    </div>
                    <div class="text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-500">
                        by <span class="text-blue-400">BusinessOS</span>
                    </div>
                </div>
                <button
                    type="button"
                    class="ml-auto grid h-9 w-9 shrink-0 place-items-center rounded-xl text-slate-400 transition hover:bg-white/10 hover:text-white lg:hidden"
                    data-sidebar-close
                    aria-label="Close navigation"
                >
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="m6 6 12 12M18 6 6 18" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <nav class="flex-1 overflow-y-auto px-3 py-5 [scrollbar-width:thin] [scrollbar-color:rgba(125,211,252,.2)_transparent]">
                @foreach($navigationGroups as $group)
                    @php
                        $visibleItems = collect($group['items'])->filter(
                            fn (array $item) => ! $item['permission'] || $permissionSlugs->has($item['permission'])
                        );
                    @endphp

                    @if($visibleItems->isNotEmpty())
                        <div class="mb-6">
                            <p class="fp-sidebar-group-title mb-2 px-3 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">
                                {{ $group['label'] }}
                            </p>
                            <div class="space-y-1">
                                @foreach($visibleItems as $item)
                                    @php($active = request()->routeIs($item['match']))
                                    <a
                                        href="{{ route($item['route']) }}"
                                        class="fp-nav-link group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition {{ $active ? 'bg-cyan-300/10 text-cyan-100 ring-1 ring-inset ring-cyan-200/15 shadow-sm shadow-cyan-950/20' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}"
                                        @if($active) aria-current="page" @endif
                                        title="{{ $item['label'] }}"
                                    >
                                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg {{ $active ? 'bg-gradient-to-br from-blue-500/25 to-cyan-300/20 text-cyan-200' : 'bg-white/5 text-slate-400 group-hover:bg-white/10 group-hover:text-cyan-200' }} text-[10px] font-extrabold tracking-wide">
                                            {{ $item['short'] }}
                                        </span>
                                        <span class="fp-nav-label min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
                                        @if($active)
                                            <span class="fp-nav-label h-1.5 w-1.5 rounded-full bg-cyan-300 shadow-[0_0_10px_rgba(34,211,238,.85)]"></span>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </nav>

            <div class="fp-sidebar-footer border-t border-white/10 p-3">
                <div class="flex items-center gap-3 rounded-2xl bg-white/5 p-2.5 ring-1 ring-inset ring-white/5">
                    <div class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-blue-500/25 to-cyan-300/20 text-sm font-bold text-cyan-100 ring-1 ring-inset ring-cyan-200/15">
                        {{ str($currentUser->name)->substr(0, 1)->upper() }}
                    </div>
                    <div class="fp-sidebar-user-copy min-w-0 flex-1">
                        <div class="truncate text-sm font-semibold text-white">{{ $currentUser->name }}</div>
                        <div class="truncate text-xs text-slate-500">{{ str($currentUser->role)->replace('_', ' ')->title() }}</div>
                    </div>
                </div>
            </div>
        </aside>

        <div class="fp-main-shell">
            <header class="fp-topbar sticky top-0 z-30 border-b backdrop-blur-xl">
                <div class="flex h-16 items-center gap-3 px-4 sm:px-6 lg:px-8">
                    <button
                        type="button"
                        class="grid h-10 w-10 place-items-center rounded-xl border border-white/10 bg-white/5 text-slate-400 transition hover:bg-white/10 hover:text-cyan-300 lg:hidden"
                        data-sidebar-open-button
                        aria-label="Open navigation"
                    >
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round"/>
                        </svg>
                    </button>

                    <button
                        type="button"
                        class="hidden h-10 w-10 place-items-center rounded-xl border border-white/10 bg-white/5 text-slate-400 transition hover:bg-white/10 hover:text-cyan-300 lg:grid"
                        data-sidebar-collapse
                        aria-label="Collapse navigation"
                        title="Toggle compact sidebar"
                    >
                        <svg viewBox="0 0 24 24" class="h-5 w-5 transition-transform" data-collapse-icon fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="m14 6-6 6 6 6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-slate-100">
                            {{ $title ?? str(optional(request()->route())->getName() ?? 'fieldpulse')->afterLast('.')->replace('-', ' ')->title() }}
                        </p>
                        <p class="hidden truncate text-xs text-slate-500 sm:block">Field operations control center</p>
                    </div>

                    <button
                        type="button"
                        class="flex h-10 items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-3 text-sm font-semibold text-slate-300 transition hover:border-cyan-300/20 hover:bg-cyan-300/5 hover:text-cyan-200"
                        data-theme-toggle
                        aria-label="Switch color theme"
                        title="Switch color theme"
                    >
                        <svg viewBox="0 0 24 24" class="h-4 w-4" data-theme-moon fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M20.2 15.1A8.6 8.6 0 0 1 8.9 3.8a8.6 8.6 0 1 0 11.3 11.3Z" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <svg viewBox="0 0 24 24" class="hidden h-4 w-4" data-theme-sun fill="none" stroke="currentColor" stroke-width="1.8">
                            <circle cx="12" cy="12" r="3.5"/>
                            <path d="M12 2.5v2M12 19.5v2M4.5 4.5l1.4 1.4M18.1 18.1l1.4 1.4M2.5 12h2M19.5 12h2M4.5 19.5l1.4-1.4M18.1 5.9l1.4-1.4" stroke-linecap="round"/>
                        </svg>
                        <span class="hidden sm:inline" data-theme-label>Dark</span>
                    </button>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button
                            class="grid h-10 w-10 place-items-center rounded-xl border border-white/10 bg-white/5 text-slate-400 transition hover:border-red-300/20 hover:bg-red-400/10 hover:text-red-300"
                            aria-label="Sign out"
                            title="Sign out"
                        >
                            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M10 5H6.8A2.8 2.8 0 0 0 4 7.8v8.4A2.8 2.8 0 0 0 6.8 19H10M14.5 8.5 18 12l-3.5 3.5M18 12H9" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                    </form>
                </div>
            </header>

            <main class="fp-content mx-auto w-full max-w-[1600px] p-4 sm:p-6 lg:p-8">
                @if(session('status'))
                    <div class="mb-5 rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 text-sm font-medium text-emerald-200 shadow-sm">
                        {{ session('status') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-5 rounded-2xl border border-red-400/20 bg-red-500/10 px-4 py-3 text-red-200 shadow-sm">
                        <ul class="list-disc space-y-1 pl-5">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>
@else
    <main class="fp-content min-h-screen">
        {{ $slot }}
    </main>
@endif

<script>
    (() => {
        const root = document.documentElement;
        const themeToggle = document.querySelector('[data-theme-toggle]');
        const themeLabel = document.querySelector('[data-theme-label]');
        const moon = document.querySelector('[data-theme-moon]');
        const sun = document.querySelector('[data-theme-sun]');
        const sidebar = document.querySelector('[data-sidebar]');

        const syncThemeUi = () => {
            const light = root.dataset.theme === 'light';
            themeLabel && (themeLabel.textContent = light ? 'Light' : 'Dark');
            moon?.classList.toggle('hidden', light);
            sun?.classList.toggle('hidden', !light);
        };

        themeToggle?.addEventListener('click', () => {
            const next = root.dataset.theme === 'light' ? 'dark' : 'light';
            root.dataset.theme = next;
            try {
                localStorage.setItem('fieldpulse-theme', next);
            } catch (_) {}
            syncThemeUi();
            window.dispatchEvent(new CustomEvent('fieldpulse:theme-changed', {detail: {theme: next}}));
        });

        const closeMobileSidebar = () => {
            root.dataset.sidebarOpen = 'false';
            document.body.style.overflow = '';
        };

        document.querySelector('[data-sidebar-open-button]')?.addEventListener('click', () => {
            root.dataset.sidebarOpen = 'true';
            document.body.style.overflow = 'hidden';
        });

        document.querySelector('[data-sidebar-close]')?.addEventListener('click', closeMobileSidebar);
        document.querySelector('[data-sidebar-overlay]')?.addEventListener('click', closeMobileSidebar);

        document.querySelector('[data-sidebar-collapse]')?.addEventListener('click', () => {
            const collapsed = root.dataset.sidebarCollapsed !== 'true';
            root.dataset.sidebarCollapsed = collapsed ? 'true' : 'false';
            try {
                localStorage.setItem('fieldpulse-sidebar-collapsed', collapsed ? 'true' : 'false');
            } catch (_) {}

            const icon = document.querySelector('[data-collapse-icon]');
            icon?.classList.toggle('rotate-180', collapsed);
        });

        sidebar?.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 1024) {
                    closeMobileSidebar();
                }
            });
        });

        const collapsed = root.dataset.sidebarCollapsed === 'true';
        document.querySelector('[data-collapse-icon]')?.classList.toggle('rotate-180', collapsed);
        syncThemeUi();
    })();
</script>
</body>
</html>
