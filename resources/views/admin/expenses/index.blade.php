<x-layouts.app>
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Expenses</h1>
        <p class="mt-1 text-sm text-slate-400">Salesman expense claims with GPS evidence and auditable finance review.</p>
    </div>

    <form class="mb-5 flex flex-wrap gap-3">
        <select name="status" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-3">
            <option value="">All statuses</option>
            @foreach(AppModelsExpense::STATUSES as $value)
                <option value="{{ $value }}" @selected($status === $value)>{{ str($value)->title() }}</option>
            @endforeach
        </select>
        <select name="category" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-3">
            <option value="">All categories</option>
            @foreach(AppModelsExpense::CATEGORIES as $value)
                <option value="{{ $value }}" @selected($category === $value)>{{ str($value)->replace('_', ' ')->title() }}</option>
            @endforeach
        </select>
        <button class="rounded-xl bg-indigo-500 px-4 py-3 font-semibold">Filter</button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-white/5">
                    <tr>
                        <th class="px-5 py-3">Expense</th>
                        <th class="px-5 py-3">Salesman</th>
                        <th class="px-5 py-3">Spent</th>
                        <th class="px-5 py-3">Category</th>
                        <th class="px-5 py-3">Amount</th>
                        <th class="px-5 py-3">Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                @forelse($expenses as $expense)
                    <tr>
                        <td class="px-5 py-4">{{ $expense->expense_number }}</td>
                        <td class="px-5 py-4">{{ $expense->salesman?->full_name ?? $expense->salesman?->user?->name ?? '—' }}</td>
                        <td class="px-5 py-4">{{ $expense->spent_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-5 py-4">{{ str($expense->category)->replace('_', ' ')->title() }}</td>
                        <td class="px-5 py-4">{{ number_format((float) $expense->amount, 2) }} {{ $expense->currency }}</td>
                        <td class="px-5 py-4">{{ str($expense->status)->title() }}</td>
                        <td class="px-5 py-4 text-right">
                            <a href="{{ route('admin.expenses.show', $expense) }}" class="rounded-lg bg-white/10 px-3 py-2">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-10 text-center text-slate-400">No expenses found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-5">{{ $expenses->links() }}</div>
</x-layouts.app>
