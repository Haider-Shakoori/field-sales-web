<x-layouts.app>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">{{ $expense->expense_number }}</h1>
            <p class="mt-1 text-sm text-slate-400">{{ $expense->salesman?->full_name ?? 'Salesman' }} · {{ str($expense->status)->title() }}</p>
        </div>
        <a href="{{ route('admin.expenses.index') }}" class="rounded-xl bg-white/10 px-4 py-2.5">Back</a>
    </div>

    <div class="grid gap-5 lg:grid-cols-3">
        <section class="rounded-2xl border border-white/10 bg-slate-900 p-5 lg:col-span-2">
            <h2 class="font-semibold">Claim details</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div><dt class="text-sm text-slate-400">Amount</dt><dd class="text-lg font-semibold">{{ number_format((float) $expense->amount, 2) }} {{ $expense->currency }}</dd></div>
                <div><dt class="text-sm text-slate-400">Category</dt><dd>{{ str($expense->category)->replace('_', ' ')->title() }}</dd></div>
                <div><dt class="text-sm text-slate-400">Spent at</dt><dd>{{ $expense->spent_at?->format('Y-m-d H:i:s') }}</dd></div>
                <div><dt class="text-sm text-slate-400">Merchant</dt><dd>{{ $expense->merchant ?? '—' }}</dd></div>
                <div><dt class="text-sm text-slate-400">Reference</dt><dd>{{ $expense->reference_number ?? '—' }}</dd></div>
                <div><dt class="text-sm text-slate-400">Device</dt><dd>{{ $expense->device?->device_uuid ?? '—' }}</dd></div>
                <div><dt class="text-sm text-slate-400">GPS</dt><dd>{{ $expense->latitude }}, {{ $expense->longitude }} (±{{ $expense->accuracy }} m)</dd></div>
                <div><dt class="text-sm text-slate-400">Reviewed by</dt><dd>{{ $expense->reviewer?->name ?? 'Not reviewed' }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-sm text-slate-400">Notes</dt><dd>{{ $expense->notes ?? '—' }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-sm text-slate-400">Review note</dt><dd>{{ $expense->review_note ?? '—' }}</dd></div>
            </dl>
        </section>

        <section class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <h2 class="font-semibold">Review state</h2>
            <p class="mt-3 text-2xl font-bold">{{ str($expense->status)->title() }}</p>
            <p class="mt-2 text-sm text-slate-400">
                @if($expense->reviewed_at)
                    Reviewed {{ $expense->reviewed_at->format('Y-m-d H:i') }}.
                @else
                    Waiting for finance review.
                @endif
            </p>
        </section>

        @if(auth()->user()->hasPermission('expenses:manage'))
            <section class="rounded-2xl border border-white/10 bg-slate-900 p-5 lg:col-span-3">
                <h2 class="font-semibold">Finance review</h2>
                @if($expense->status === 'pending')
                    <form method="POST" action="{{ route('admin.expenses.status', $expense) }}" class="mt-4 grid gap-3 md:grid-cols-[220px_1fr_auto]">
                        @csrf
                        @method('PATCH')
                        <select name="status" class="rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
                            <option value="approved">Approve</option>
                            <option value="rejected">Reject</option>
                            <option value="cancelled">Cancel</option>
                        </select>
                        <input name="review_note" class="rounded-xl border border-white/10 bg-slate-950 px-4 py-3" placeholder="Reason required for reject/cancel">
                        <button class="rounded-xl bg-indigo-500 px-5 py-3 font-semibold">Submit review</button>
                    </form>
                @else
                    <p class="mt-3 text-sm text-slate-400">Reviewed expenses are immutable. Any financial correction should be recorded separately instead of rewriting this claim.</p>
                @endif
            </section>
        @endif
    </div>
</x-layouts.app>
