<x-layouts.app>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">{{ $visit->customer?->name ?? 'Customer visit' }}</h1>
            <p class="mt-1 text-sm text-slate-400">{{ $visit->is_planned ? 'Planned' : 'Unplanned' }} · {{ ucfirst($visit->status) }}</p>
        </div>
        <a href="{{ route('admin.visits.index') }}" class="rounded-xl bg-white/10 px-4 py-2.5">Back to visits</a>
    </div>

    <div class="grid gap-5 lg:grid-cols-2">
        <section class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <h2 class="font-semibold">Visit detail</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div><dt class="text-sm text-slate-400">Salesman</dt><dd>{{ $visit->salesman?->full_name ?? '—' }}</dd></div>
                <div><dt class="text-sm text-slate-400">Route</dt><dd>{{ $visit->route?->name ?? 'Unplanned' }}</dd></div>
                <div><dt class="text-sm text-slate-400">Check-in</dt><dd>{{ $visit->checked_in_at?->format('Y-m-d H:i:s') }}</dd></div>
                <div><dt class="text-sm text-slate-400">Check-out</dt><dd>{{ $visit->checked_out_at?->format('Y-m-d H:i:s') ?? 'Active' }}</dd></div>
                <div><dt class="text-sm text-slate-400">Duration</dt><dd>{{ $visit->duration_seconds === null ? '—' : round($visit->duration_seconds / 60, 1).' min' }}</dd></div>
                <div><dt class="text-sm text-slate-400">Outcome</dt><dd>{{ $visit->outcome ? str($visit->outcome)->replace('_', ' ')->title() : '—' }}</dd></div>
                <div><dt class="text-sm text-slate-400">Check-in distance</dt><dd>{{ $visit->checkin_distance_meters === null ? 'No customer coordinates' : round($visit->checkin_distance_meters, 1).' m' }}</dd></div>
                <div><dt class="text-sm text-slate-400">Geofence</dt><dd>{{ $visit->checkin_within_geofence === null ? 'Unknown' : ($visit->checkin_within_geofence ? 'Inside' : 'Outside') }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-sm text-slate-400">Notes</dt><dd>{{ $visit->notes ?? '—' }}</dd></div>
            </dl>
        </section>

        <section class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <h2 class="font-semibold">Suspicious flags</h2>
            <div class="mt-4 space-y-3">
                @forelse($visit->suspiciousFlags as $flag)
                    <div class="rounded-xl border border-amber-400/20 bg-amber-500/10 p-3">
                        <div class="font-medium">{{ str($flag->reason_code)->replace('_', ' ')->title() }} · {{ ucfirst($flag->severity) }}</div>
                        @if($flag->details)<pre class="mt-2 whitespace-pre-wrap text-xs text-slate-300">{{ json_encode($flag->details, JSON_PRETTY_PRINT) }}</pre>@endif
                    </div>
                @empty
                    <p class="text-sm text-slate-400">No suspicious flags.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-2xl border border-white/10 bg-slate-900 p-5 lg:col-span-2">
            <h2 class="font-semibold">Photos</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @forelse($visit->photos as $photo)
                    <a href="{{ \Illuminate\Support\Facades\Storage::disk($photo->disk)->url($photo->path) }}" target="_blank" class="rounded-xl bg-slate-950 p-3">
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk($photo->disk)->url($photo->path) }}" alt="Visit photo" class="aspect-video w-full rounded-lg object-cover">
                        <div class="mt-2 text-xs text-slate-400">{{ $photo->captured_at?->format('Y-m-d H:i:s') }}</div>
                    </a>
                @empty
                    <p class="text-sm text-slate-400">No photos attached.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.app>
