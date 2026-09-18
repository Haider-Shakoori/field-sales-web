<!doctype html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="color-scheme" content="dark light">
    <title>{{ $title ?? 'Field Sales' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: { boxShadow: { soft: '0 18px 50px rgba(15,23,42,.12)' } } }
        }
    </script>
</head>
<body class="min-h-full bg-slate-100 text-slate-900 dark:bg-slate-950 dark:text-slate-100" x-data="{open:false, collapsed:false}">
@auth
<div class="min-h-screen lg:flex">
    <aside class="fixed inset-y-0 left-0 z-40 w-72 -translate-x-full border-r border-white/10 bg-slate-950 text-slate-100 transition lg:sticky lg:top-0 lg:h-screen lg:translate-x-0"
           :class="{'translate-x-0':open,'lg:w-20':collapsed}">
        <div class="flex h-16 items-center gap-3 border-b border-white/10 px-5">
            <div class="grid h-9 w-9 place-items-center rounded-xl bg-indigo-500 font-black">FS</div>
            <div x-show="!collapsed" class="min-w-0">
                <p class="font-bold leading-5">Field Sales</p>
                <p class="text-xs text-slate-400">Operations Console</p>
            </div>
        </div>
        <nav class="space-y-1 p-3 text-sm">
            @php
                $nav = [
                    ['Dashboard','admin.dashboard','M4 13h6V4H4v9Zm0 7h6v-5H4v5Zm10 0h6v-9h-6v9Zm0-16v5h6V4h-6Z'],
                    ['Attendance','admin.attendance','M12 8v5l3 2'],
                    ['Current Locations','admin.locations','M12 21s7-4.35 7-11a7 7 0 1 0-14 0c0 6.65 7 11 7 11Z'],
                    ['Customers','admin.customers','M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2'],
                    ['Visits','admin.visits','M9 11l3 3L22 4'],
                    ['Orders','admin.orders','M6 2l1 4h10l1-4'],
                    ['Collections','admin.collections','M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7H14a3.5 3.5 0 0 1 0 7H6'],
                    ['Expenses','admin.expenses','M3 3h18v18H3z'],
                    ['Targets','admin.targets','M12 2a10 10 0 1 0 10 10'],
                    ['Tracking Settings','tracking.edit','M12 15.5A3.5 3.5 0 1 0 12 8a3.5 3.5 0 0 0 0 7.5Z'],
                ];
            @endphp
            @foreach($nav as [$label,$routeName,$icon])
                <a href="{{ route($routeName) }}"
                   class="flex items-center gap-3 rounded-xl px-3 py-2.5 transition {{ request()->routeIs($routeName) ? 'bg-indigo-500 text-white' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                    <span x-show="!collapsed">{{ $label }}</span>
                </a>
            @endforeach
        </nav>
        <button @click="collapsed=!collapsed" class="absolute bottom-4 right-3 hidden rounded-lg p-2 text-slate-400 hover:bg-white/10 lg:block">
            <span x-text="collapsed?'→':'←'"></span>
        </button>
    </aside>

    <div class="min-w-0 flex-1">
        <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-slate-200/80 bg-white/90 px-4 backdrop-blur dark:border-white/10 dark:bg-slate-900/90 sm:px-6">
            <div class="flex items-center gap-3">
                <button @click="open=true" class="rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-white/10 lg:hidden">☰</button>
                <div>
                    <p class="text-sm font-semibold">{{ auth()->user()->tenant?->name ?? 'Field Sales' }}</p>
                    <p class="text-xs text-slate-500">{{ auth()->user()->name }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="document.documentElement.classList.toggle('dark')" class="rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-white/10">Theme</button>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="rounded-xl bg-slate-900 px-3 py-2 text-sm font-semibold text-white dark:bg-white dark:text-slate-900">Sign out</button></form>
            </div>
        </header>
        <main class="p-4 sm:p-6 lg:p-8">{{ $slot }}</main>
    </div>
</div>
<div x-show="open" @click="open=false" class="fixed inset-0 z-30 bg-black/60 lg:hidden" x-cloak></div>
@else
<main class="min-h-screen">{{ $slot }}</main>
@endauth
</body>
</html>
