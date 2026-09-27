@props([
    'value' => '',
    'placeholder' => 'Search...',
])

@php
    $preserved = collect(request()->except(['search', 'page']))
        ->filter(fn ($value) => is_scalar($value) && (string) $value !== '');
    $clearQuery = http_build_query($preserved->all());
    $clearUrl = url()->current().($clearQuery !== '' ? '?'.$clearQuery : '');
@endphp

<form method="GET" action="{{ url()->current() }}" class="mb-5 flex flex-wrap items-center gap-2">
    @foreach($preserved as $name => $preservedValue)
        <input type="hidden" name="{{ $name }}" value="{{ $preservedValue }}">
    @endforeach
    <div class="relative min-w-[240px] flex-1 sm:max-w-md">
        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-500">⌕</span>
        <input
            type="search"
            name="search"
            value="{{ $value }}"
            placeholder="{{ $placeholder }}"
            class="w-full rounded-xl border border-white/10 bg-slate-900 py-3 pl-9 pr-4 text-sm placeholder:text-slate-500"
        >
    </div>
    <button class="rounded-xl bg-indigo-500 px-4 py-3 text-sm font-semibold hover:bg-indigo-400">Search</button>
    @if(trim((string) $value) !== '')
        <a href="{{ $clearUrl }}" class="rounded-xl bg-white/10 px-4 py-3 text-sm font-semibold hover:bg-white/20">Clear</a>
    @endif
</form>
