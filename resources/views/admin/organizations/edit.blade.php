<x-layouts.app>
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Edit {{ $organization->name }}</h1>
        <p class="mt-1 text-sm text-slate-400">Organization identity and operational timezone. The slug is the company identifier used at sign-in.</p>
    </div>

    <form method="POST" action="{{ route('admin.organizations.update', $organization) }}" class="max-w-3xl space-y-6">
        @csrf
        @method('PUT')

        <section class="space-y-5 rounded-2xl border border-white/10 bg-slate-900 p-6">
            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm text-slate-300">Company name</label>
                    <input name="name" value="{{ old('name', $organization->name) }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
                </div>
                <div>
                    <label class="mb-2 block text-sm text-slate-300">Company identifier (slug)</label>
                    <input name="slug" value="{{ old('slug', $organization->slug) }}" pattern="[a-z0-9]+(-[a-z0-9]+)*" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
                </div>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm text-slate-300">Timezone</label>
                    <select name="timezone" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
                        @foreach($timezones as $timezone)
                            <option value="{{ $timezone }}" @selected(old('timezone', $organization->timezone) === $timezone)>{{ $timezone }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-sm text-slate-300">Billing / contact email</label>
                    <input type="email" name="contact_email" value="{{ old('contact_email', $organization->contact_email) }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
                </div>
            </div>

            <div class="flex flex-wrap gap-6 rounded-xl bg-white/5 px-4 py-3 text-sm">
                <div>
                    <span class="text-slate-400">Status:</span>
                    <span class="font-medium {{ $organization->subscription_status === 'active' ? 'text-emerald-300' : 'text-amber-300' }}">{{ str($organization->subscription_status)->title() }}</span>
                </div>
                <div>
                    <span class="text-slate-400">UUID:</span>
                    <span class="font-mono text-xs text-slate-300">{{ $organization->uuid }}</span>
                </div>
                <div>
                    <span class="text-slate-400">Created:</span>
                    <span class="text-slate-300">{{ $organization->created_at?->format('Y-m-d') }}</span>
                </div>
            </div>
        </section>

        <div class="flex gap-3">
            <button class="rounded-xl bg-indigo-500 px-5 py-3 font-semibold hover:bg-indigo-400">Save organization</button>
            <a href="{{ route('admin.organizations.index') }}" class="rounded-xl bg-white/10 px-5 py-3 hover:bg-white/20">Cancel</a>
        </div>
    </form>
</x-layouts.app>
