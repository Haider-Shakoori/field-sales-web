<x-layouts.app>
<div class="mx-auto mt-20 max-w-md rounded-3xl border border-white/10 bg-slate-900 p-8 shadow-2xl">
    <div class="mb-8">
        <p class="font-semibold text-indigo-400">Administrator access</p>
        <h1 class="mt-2 text-3xl font-bold">Welcome back</h1>
        <p class="mt-2 text-slate-400">Manage your company field-sales workspace.</p>
    </div>

    @if($errors->any())
        <div class="mb-4 rounded-xl bg-red-500/10 p-3 text-red-300">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="/login" class="space-y-5">
        @csrf

        <label class="block">
            <span class="text-sm text-slate-300">Email</span>
            <input name="email" type="email" value="{{ old('email') }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
        </label>

        <label class="block">
            <span class="text-sm text-slate-300">Company identifier <span class="text-slate-500">(only needed when the same email belongs to multiple companies)</span></span>
            <input name="tenant" value="{{ old('tenant') }}" placeholder="Company slug or UUID" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
        </label>

        <label class="block">
            <span class="text-sm text-slate-300">Password</span>
            <input type="password" name="password" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
        </label>

        <button class="w-full rounded-xl bg-indigo-500 px-4 py-3 font-semibold hover:bg-indigo-400">Sign in</button>
    </form>
</div>
</x-layouts.app>
