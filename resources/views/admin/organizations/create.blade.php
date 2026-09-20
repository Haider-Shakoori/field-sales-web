<x-layouts.app>
    <div class="mb-6">
        <h1 class="text-2xl font-bold">New organization</h1>
        <p class="mt-1 text-sm text-slate-400">Provisioning creates the company, its default roles and permissions, tracking policy, a headquarters branch and the first company administrator.</p>
    </div>

    <form method="POST" action="{{ route('admin.organizations.store') }}" class="max-w-3xl space-y-6">
        @csrf

        <section class="space-y-5 rounded-2xl border border-white/10 bg-slate-900 p-6">
            <h2 class="font-semibold">Company</h2>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm text-slate-300">Company name</label>
                    <input name="name" value="{{ old('name') }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
                </div>
                <div>
                    <label class="mb-2 block text-sm text-slate-300">Company identifier (slug)</label>
                    <input name="slug" value="{{ old('slug') }}" placeholder="acme-field-sales" pattern="[a-z0-9]+(-[a-z0-9]+)*" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
                    <p class="mt-1 text-xs text-slate-500">Lowercase letters, numbers and dashes. Used to sign in when an email exists in multiple companies.</p>
                </div>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm text-slate-300">Timezone</label>
                    <select name="timezone" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
                        @foreach($timezones as $timezone)
                            <option value="{{ $timezone }}" @selected(old('timezone', 'UTC') === $timezone)>{{ $timezone }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-sm text-slate-300">Billing / contact email</label>
                    <input type="email" name="contact_email" value="{{ old('contact_email') }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
                </div>
            </div>
        </section>

        <section class="space-y-5 rounded-2xl border border-white/10 bg-slate-900 p-6">
            <h2 class="font-semibold">First administrator</h2>
            <p class="text-sm text-slate-400">This account receives the Company Admin role and can manage users, roles, branches and policy immediately.</p>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm text-slate-300">Full name</label>
                    <input name="admin_name" value="{{ old('admin_name') }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
                </div>
                <div>
                    <label class="mb-2 block text-sm text-slate-300">Email</label>
                    <input type="email" name="admin_email" value="{{ old('admin_email') }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
                </div>
            </div>

            <div class="max-w-sm">
                <label class="mb-2 block text-sm text-slate-300">Temporary password</label>
                <input type="password" name="admin_password" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" minlength="8" required>
                <p class="mt-1 text-xs text-slate-500">At least 8 characters. Share it securely; the administrator can rotate it later.</p>
            </div>
        </section>

        <div class="flex gap-3">
            <button class="rounded-xl bg-indigo-500 px-5 py-3 font-semibold hover:bg-indigo-400">Create organization</button>
            <a href="{{ route('admin.organizations.index') }}" class="rounded-xl bg-white/10 px-5 py-3 hover:bg-white/20">Cancel</a>
        </div>
    </form>
</x-layouts.app>
