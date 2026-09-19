<x-layouts.app>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">Notifications</h1>
            <p class="mt-1 text-sm text-slate-400">Operational updates for your account.</p>
        </div>
        <form method="POST" action="{{ route('admin.notifications.read-all') }}">
            @csrf
            @method('PATCH')
            <button class="rounded-lg bg-white/10 px-4 py-2 text-sm hover:bg-white/20">Mark all read</button>
        </form>
    </div>

    <section class="mb-6 rounded-2xl border border-white/10 bg-slate-900 p-5">
        <h2 class="font-semibold">Preferences</h2>
        <form method="POST" action="{{ route('admin.notifications.preferences') }}" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @csrf
            @method('PUT')
            @foreach([
                'database_enabled' => 'In-app notifications',
                'push_enabled' => 'Push notifications',
                'order_updates' => 'Order updates',
                'collection_updates' => 'Collection updates',
                'expense_updates' => 'Expense updates',
                'suspicious_alerts' => 'Suspicious activity alerts',
            ] as $field => $label)
                <label class="flex items-center justify-between gap-4 rounded-xl border border-white/10 bg-slate-950 px-4 py-3 text-sm">
                    <span>{{ $label }}</span>
                    <input type="hidden" name="{{ $field }}" value="0">
                    <input type="checkbox" name="{{ $field }}" value="1" @checked($preferences->{$field}) class="h-4 w-4">
                </label>
            @endforeach
            <div class="sm:col-span-2 lg:col-span-3">
                <button class="rounded-lg bg-sky-500 px-4 py-2 font-semibold text-slate-950">Save preferences</button>
            </div>
        </form>
    </section>

    <section class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
        <div class="divide-y divide-white/10">
            @forelse($notifications as $notification)
                <div class="flex flex-wrap items-start gap-4 px-5 py-4 {{ $notification->read_at ? 'opacity-70' : '' }}">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium">{{ $notification->title }}</span>
                            <span class="rounded-full bg-white/5 px-2 py-1 text-xs text-slate-400">{{ str($notification->priority)->title() }}</span>
                            @unless($notification->read_at)
                                <span class="rounded-full bg-sky-500/10 px-2 py-1 text-xs text-sky-300">Unread</span>
                            @endunless
                        </div>
                        <p class="mt-2 text-sm text-slate-300">{{ $notification->message }}</p>
                        <p class="mt-2 text-xs text-slate-500">{{ $notification->created_at?->format('Y-m-d H:i') }} · {{ str($notification->category)->replace('_', ' ')->title() }}</p>
                    </div>
                    @unless($notification->read_at)
                        <form method="POST" action="{{ route('admin.notifications.read', $notification) }}">
                            @csrf
                            @method('PATCH')
                            <button class="rounded-lg bg-white/10 px-3 py-2 text-xs hover:bg-white/20">Mark read</button>
                        </form>
                    @endunless
                </div>
            @empty
                <div class="px-5 py-10 text-center text-slate-400">No notifications yet.</div>
            @endforelse
        </div>
    </section>

    <div class="mt-5">{{ $notifications->links() }}</div>
</x-layouts.app>
