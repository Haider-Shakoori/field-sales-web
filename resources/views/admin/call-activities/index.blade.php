<x-layouts.app>
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Customer calls</h1>
        <p class="mt-1 text-sm text-slate-400">Optional call outcomes and notes recorded by the field team.</p>
    </div>

    <form class="mb-5 flex flex-wrap gap-3">
        <select name="outcome" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-3">
            <option value="">All outcomes</option>
            @foreach(\App\Models\CustomerCallActivity::OUTCOMES as $value)
                <option value="{{ $value }}" @selected($outcome === $value)>{{ str($value)->replace('_', ' ')->title() }}</option>
            @endforeach
        </select>
        <button class="rounded-xl bg-indigo-500 px-4 py-3 font-semibold">Filter</button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-white/5"><tr><th class="px-5 py-3">Customer</th><th class="px-5 py-3">Salesman</th><th class="px-5 py-3">Called</th><th class="px-5 py-3">Number</th><th class="px-5 py-3">Outcome</th><th class="px-5 py-3">Notes</th></tr></thead>
                <tbody class="divide-y divide-white/10">
                @forelse($activities as $activity)
                    <tr>
                        <td class="px-5 py-4"><a class="hover:underline" href="{{ route('admin.customers.show', $activity->customer) }}">{{ $activity->customer?->name ?? 'Deleted customer' }}</a></td>
                        <td class="px-5 py-4">{{ $activity->salesman?->full_name ?? $activity->salesman?->user?->name ?? '—' }}</td>
                        <td class="px-5 py-4">{{ $activity->called_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-5 py-4">{{ $activity->phone_number ?? '—' }}</td>
                        <td class="px-5 py-4">{{ $activity->outcome ? str($activity->outcome)->replace('_', ' ')->title() : 'Not recorded' }}</td>
                        <td class="max-w-sm px-5 py-4">{{ $activity->notes ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-slate-400">No call activities found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-5">{{ $activities->links() }}</div>
</x-layouts.app>
