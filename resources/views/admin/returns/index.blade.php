<x-layouts.app>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">{{ __('Returns') }}</h1>
            <p class="mt-1 text-sm text-slate-400">{{ __('Review customer product returns and damaged goods reported by salesmen.') }}</p>
        </div>
        <form method="GET" class="flex gap-2">
            <select name="status" class="rounded-xl border border-white/10 bg-slate-950 px-4 py-2.5">
                <option value="">{{ __('All statuses') }}</option>
                @foreach(\App\Models\SalesReturn::STATUSES as $value)
                    <option value="{{ $value }}" @selected($status === $value)>{{ __(str($value)->title()->toString()) }}</option>
                @endforeach
            </select>
            <button class="rounded-xl bg-white/10 px-4 py-2.5">{{ __('Filter') }}</button>
        </form>
    </div>

    <div class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-white/5">
                <tr>
                    <th class="px-5 py-3">{{ __('Return') }}</th>
                    <th class="px-5 py-3">{{ __('Customer') }}</th>
                    <th class="px-5 py-3">{{ __('Salesman') }}</th>
                    <th class="px-5 py-3">{{ __('Items') }}</th>
                    <th class="px-5 py-3">{{ __('Status') }}</th>
                    <th class="px-5 py-3">{{ __('Returned at') }}</th>
                    <th class="px-5 py-3"></th>
                </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                @forelse($returns as $return)
                    <tr>
                        <td class="px-5 py-4 font-mono text-xs">{{ $return->return_number }}</td>
                        <td class="px-5 py-4">{{ $return->customer?->name }}</td>
                        <td class="px-5 py-4">{{ $return->salesman?->full_name }}</td>
                        <td class="px-5 py-4">{{ $return->items_count }}</td>
                        <td class="px-5 py-4">{{ __(str($return->status)->title()->toString()) }}</td>
                        <td class="px-5 py-4">{{ $return->returned_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-5 py-4 text-right"><a href="{{ route('admin.returns.show', $return) }}" class="rounded-lg bg-white/10 px-3 py-2">{{ __('View') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-10 text-center text-slate-400">{{ __('No returns recorded yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($returns->hasPages())<div class="border-t border-white/10 px-5 py-4">{{ $returns->links() }}</div>@endif
    </div>
</x-layouts.app>
