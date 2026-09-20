<x-layouts.app>
<div class="relative mx-auto mt-12 max-w-md">
    <div class="absolute -inset-x-6 -top-10 h-40 rounded-full bg-indigo-500/10 blur-3xl" aria-hidden="true"></div>

    <div class="relative rounded-3xl border border-white/10 bg-slate-900/80 p-8 shadow-2xl shadow-slate-950/60 backdrop-blur">
        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-sky-400 text-xl font-black text-white shadow-lg shadow-indigo-500/30">F</span>

        <div class="mb-8 mt-6">
            <p class="font-semibold text-indigo-400">Administrator access</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight">Welcome back</h1>
            <p class="mt-2 text-slate-400">Manage company attendance and location policy.</p>
        </div>

        @if($errors->any())
            <div class="mb-4 rounded-xl border border-red-400/20 bg-red-500/10 p-3 text-red-300">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="/login" class="space-y-5">
            @csrf

            <label class="block">
                <span class="text-sm font-medium text-slate-300">Email</span>
                <input type="email" name="email" value="{{ old('email') }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
            </label>

            <label class="block">
                <span class="text-sm font-medium text-slate-300">Company identifier <span class="text-slate-500">(only if this email belongs to multiple companies)</span></span>
                <input name="tenant" value="{{ old('tenant') }}" placeholder="Company slug or UUID" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
            </label>

            <label class="block">
                <span class="text-sm font-medium text-slate-300">Password</span>
                <input type="password" name="password" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
            </label>

            <button class="w-full rounded-xl bg-gradient-to-r from-indigo-500 to-sky-500 px-4 py-3 font-semibold text-white shadow-lg shadow-indigo-500/25 hover:from-indigo-400 hover:to-sky-400">Sign in</button>
        </form>
    </div>
</div>
</x-layouts.app>
