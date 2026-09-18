<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $title ?? 'Field Sales' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
<nav class="border-b border-white/10 bg-slate-900/80 backdrop-blur">
    <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
        <div>
            <p class="text-xl font-bold">Field Sales</p>
            <p class="text-xs text-slate-400">Operations Console</p>
        </div>

        @php($webGuardName = auth()->guard()->getName())
        @if(session()->has($webGuardName))
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="rounded-lg bg-white/10 px-3 py-2 text-sm hover:bg-white/20">Sign out</button>
            </form>
        @endif
    </div>
</nav>

<main class="mx-auto max-w-6xl p-6">{{ $slot }}</main>
</body>
</html>
