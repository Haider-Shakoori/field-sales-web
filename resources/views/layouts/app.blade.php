<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $title ?? 'Field Sales' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
@php
    $webGuardName = auth()->guard()->getName();
    $tenantReady = app(\App\Tenancy\TenantContext::class)->hasTenant();
    $currentUser = $tenantReady ? auth()->user() : null;
@endphp

<nav class="border-b border-white/10 bg-slate-900/80 backdrop-blur">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-4 px-6 py-4">
        <div class="mr-4">
            <p class="text-xl font-bold">Field Sales</p>
            <p class="text-xs text-slate-400">Operations Console</p>
        </div>

        @if($currentUser)
            <div class="flex flex-1 flex-wrap items-center gap-2 text-sm">
                @if($currentUser->hasPermission('users:view'))
                    <a href="{{ route('admin.users.index') }}" class="rounded-lg px-3 py-2 hover:bg-white/10">Users</a>
                @endif
                @if($currentUser->hasPermission('roles:view'))
                    <a href="{{ route('admin.roles.index') }}" class="rounded-lg px-3 py-2 hover:bg-white/10">Roles</a>
                @endif
                @if($currentUser->hasPermission('branches:view'))
                    <a href="{{ route('admin.branches.index') }}" class="rounded-lg px-3 py-2 hover:bg-white/10">Branches</a>
                @endif
                @if($currentUser->hasPermission('sales-team:view'))
                    <a href="{{ route('admin.salesmen.index') }}" class="rounded-lg px-3 py-2 hover:bg-white/10">Salesmen</a>
                    <a href="{{ route('admin.supervisors.index') }}" class="rounded-lg px-3 py-2 hover:bg-white/10">Supervisors</a>
                    <a href="{{ route('admin.devices.index') }}" class="rounded-lg px-3 py-2 hover:bg-white/10">Devices</a>
                    <a href="{{ route('admin.salesman-assignments.index') }}" class="rounded-lg px-3 py-2 hover:bg-white/10">Salesman assignments</a>
                    <a href="{{ route('admin.supervisor-assignments.index') }}" class="rounded-lg px-3 py-2 hover:bg-white/10">Supervisor assignments</a>
                @endif
                @if($currentUser->hasPermission('customers:view'))
                    <a href="{{ route('admin.customers.index') }}" class="rounded-lg px-3 py-2 hover:bg-white/10">Customers</a>
                    <a href="{{ route('admin.territories.index') }}" class="rounded-lg px-3 py-2 hover:bg-white/10">Territories</a>
                    <a href="{{ route('admin.routes.index') }}" class="rounded-lg px-3 py-2 hover:bg-white/10">Routes</a>
                    <a href="{{ route('admin.call-activities.index') }}" class="rounded-lg px-3 py-2 hover:bg-white/10">Calls</a>
                @endif
                @if($currentUser->hasPermission('visits:view'))
                    <a href="{{ route('admin.visits.index') }}" class="rounded-lg px-3 py-2 hover:bg-white/10">Visits</a>
                @endif
                @if($currentUser->hasPermission('catalog:view'))
                    <a href="{{ route('admin.products.index') }}" class="rounded-lg px-3 py-2 hover:bg-white/10">Products</a>
                    <a href="{{ route('admin.price-lists.index') }}" class="rounded-lg px-3 py-2 hover:bg-white/10">Price lists</a>
                @endif
                @if($currentUser->hasPermission('audit:view'))
                    <a href="{{ route('admin.audit.index') }}" class="rounded-lg px-3 py-2 hover:bg-white/10">Audit</a>
                @endif
                @if($currentUser->hasPermission('settings:view'))
                    <a href="{{ route('tracking.edit') }}" class="rounded-lg px-3 py-2 hover:bg-white/10">Tracking settings</a>
                @endif
            </div>
        @else
            <div class="flex-1"></div>
        @endif

        @if(session()->has($webGuardName))
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="rounded-lg bg-white/10 px-3 py-2 text-sm hover:bg-white/20">Sign out</button>
            </form>
        @endif
    </div>
</nav>

<main class="mx-auto max-w-7xl p-6">
    @if(session('status'))
        <div class="mb-5 rounded-xl border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 text-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    @if($errors->any())
        <div class="mb-5 rounded-xl border border-red-400/20 bg-red-500/10 px-4 py-3 text-red-200">
            <ul class="list-disc space-y-1 pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{ $slot }}
</main>
</body>
</html>
