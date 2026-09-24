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
            <h2 class="font-semibold">{{ __('Visit form submissions') }}</h2>
            <div class="mt-4 space-y-4">
                @forelse($visit->formSubmissions as $submission)
                    <div class="rounded-xl border border-white/10 bg-slate-950 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <div class="font-medium">{{ $submission->template_name }}</div>
                                <div class="mt-1 text-xs text-slate-400">
                                    v{{ $submission->template_version }} · {{ $submission->submitted_at?->format('Y-m-d H:i:s') }}
                                </div>
                            </div>
                        </div>
                        <dl class="mt-4 grid gap-3 md:grid-cols-2">
                            @foreach($submission->answers as $answer)
                                <div class="rounded-lg bg-white/5 p-3">
                                    <dt class="text-xs text-slate-400">{{ $answer->question_label }}</dt>
                                    <dd class="mt-1 text-sm">
                                        @php($answerValue = $answer->value)
                                        @if($answer->question_type === 'photo' && isset($answerValue['photo_id']))
                                            {{ __('Photo') }}: {{ $answerValue['photo_id'] }}
                                        @elseif(is_array($answerValue['value'] ?? null))
                                            {{ implode(', ', $answerValue['value']) }}
                                        @elseif(is_bool($answerValue['value'] ?? null))
                                            {{ $answerValue['value'] ? __('Yes') : __('No') }}
                                        @else
                                            {{ $answerValue['value'] ?? '—' }}
                                        @endif
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                @empty
                    <p class="text-sm text-slate-400">{{ __('No visit forms submitted.') }}</p>
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

<section class="mt-5 rounded-2xl border border-white/10 bg-slate-900"><div class="border-b border-white/10 px-5 py-4"><h2 class="font-semibold">{{ __('Voice notes') }}</h2></div><div class="divide-y divide-white/10">@forelse($visit->voiceNotes as $note)<div class="px-5 py-4"><div class="flex flex-wrap items-center justify-between gap-3"><div><p class="text-sm font-medium">{{ $note->user?->name ?? __('Salesman') }}</p><p class="mt-1 text-xs text-slate-500">{{ $note->captured_at?->format('Y-m-d H:i') }}@if($note->duration_seconds) · {{ $note->duration_seconds }}s @endif</p></div><audio controls preload="none" src="{{ route('admin.visit-voice-notes.show',$note) }}" class="max-w-full"></audio></div></div>@empty<div class="px-5 py-8 text-center text-sm text-slate-500">{{ __('No voice notes for this visit.') }}</div>@endforelse</div></section>
</x-layouts.app>