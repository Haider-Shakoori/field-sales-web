<x-layouts.app>
<div class="space-y-5">
  <div><h1 class="text-3xl font-black">Current locations</h1><p class="mt-1 text-sm text-slate-500">Latest server-accepted GPS state. Mock-location is a review signal, not an automatic accusation.</p></div>
  <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-900">
    <table class="min-w-full text-sm"><thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-white/5"><tr><th class="p-4">Salesman</th><th class="p-4">Position</th><th class="p-4">Accuracy</th><th class="p-4">Speed</th><th class="p-4">Battery</th><th class="p-4">Recorded</th><th class="p-4">State</th></tr></thead>
    <tbody class="divide-y divide-slate-100 dark:divide-white/10">@forelse($rows as $row)@php($fresh=$row->recorded_at?->gte($staleBefore))
      <tr><td class="p-4 font-semibold">{{ $row->salesman?->user?->name ?? '—' }}<div class="text-xs font-normal text-slate-500">{{ $row->salesman?->employee_code }}</div></td><td class="p-4 font-mono text-xs">{{ $row->latitude }}, {{ $row->longitude }}</td><td class="p-4">{{ $row->horizontal_accuracy }} m</td><td class="p-4">{{ $row->speed ?? '—' }}</td><td class="p-4">{{ $row->battery_level !== null ? $row->battery_level.'%' : '—' }}</td><td class="p-4">{{ $row->recorded_at?->diffForHumans() }}</td><td class="p-4"><div class="flex gap-2"><span class="rounded-full px-2 py-1 text-xs {{ $fresh?'bg-emerald-100 text-emerald-700':'bg-amber-100 text-amber-700' }}">{{ $fresh?'Fresh':'Stale' }}</span>@if($row->is_mock_location)<span class="rounded-full bg-rose-100 px-2 py-1 text-xs text-rose-700">Mock</span>@endif</div></td></tr>
    @empty<tr><td colspan="7" class="p-10 text-center text-slate-500">No GPS locations have been received yet.</td></tr>@endforelse</tbody></table>
  </div>
</div>
</x-layouts.app>
