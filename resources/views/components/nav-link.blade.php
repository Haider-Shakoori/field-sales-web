@props(['href', 'icon' => null, 'active' => false])

@php
    $base = 'fp-nav-link group flex w-full items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition';
    $state = $active
        ? 'bg-indigo-500/15 text-indigo-100 ring-1 ring-inset ring-indigo-400/30'
        : 'text-slate-400 hover:bg-white/5 hover:text-slate-100';
@endphp

<a href="{{ $href }}" {{ $attributes->merge(['class' => $base.' '.$state]) }}>
    @if($icon)
        <svg class="h-4 w-4 shrink-0 {{ $active ? 'text-indigo-300' : 'text-slate-500 transition group-hover:text-slate-300' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
            @switch($icon)
                @case('home')
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 22V12h6v10"/>
                    @break
                @case('users')
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    @break
                @case('shield')
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    @break
                @case('building')
                    <rect x="4" y="2" width="16" height="20" rx="2"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 22v-4h6v4M8 6h.01M12 6h.01M16 6h.01M8 10h.01M12 10h.01M16 10h.01M8 14h.01M12 14h.01M16 14h.01"/>
                    @break
                @case('user')
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                    @break
                @case('user-plus')
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="8.5" cy="7" r="4"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 8v6M23 11h-6"/>
                    @break
                @case('device')
                    <rect x="5" y="2" width="14" height="20" rx="2"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 18h.01"/>
                    @break
                @case('clipboard')
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
                    <rect x="8" y="2" width="8" height="4" rx="1"/>
                    @break
                @case('briefcase')
                    <rect x="2" y="7" width="20" height="14" rx="2"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                    @break
                @case('map')
                    <path stroke-linecap="round" stroke-linejoin="round" d="M1 6v16l7-4 8 4 7-4V2l-7 4-8-4-7 4z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 2v16M16 6v16"/>
                    @break
                @case('map-pin')
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                    <circle cx="12" cy="10" r="3"/>
                    @break
                @case('phone')
                    <path stroke-linecap="round" stroke-linejoin="round" d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>
                    @break
                @case('cart')
                    <circle cx="9" cy="21" r="1"/>
                    <circle cx="20" cy="21" r="1"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                    @break
                @case('banknotes')
                    <rect x="2" y="6" width="20" height="12" rx="2"/>
                    <circle cx="12" cy="12" r="2.5"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 12h.01M18 12h.01"/>
                    @break
                @case('receipt')
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h8M8 11h8M8 15h5"/>
                    @break
                @case('flag')
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 22v-7"/>
                    @break
                @case('calendar-check')
                    <rect x="3" y="4" width="18" height="18" rx="2"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 2v4M8 2v4M3 10h18"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="m9 16 2 2 4-4"/>
                    @break
                @case('cube')
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.27 6.96 12 12.01l8.73-5.05"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 22.08V12"/>
                    @break
                @case('tag')
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01"/>
                    @break
                @case('bell')
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    @break
                @case('alert')
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4M12 17h.01"/>
                    @break
                @case('chart')
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 20V10M12 20V4M6 20v-6"/>
                    @break
                @case('document-search')
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 2v6h6"/>
                    <circle cx="11.5" cy="14.5" r="2.5"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="m13.5 16.5 2 2"/>
                    @break
                @case('cog')
                    <circle cx="12" cy="12" r="3"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33h0a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51h0a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82v0a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                    @break
                @default
                    <circle cx="12" cy="12" r="3"/>
            @endswitch
        </svg>
    @endif
    <span class="truncate">{{ $slot }}</span>
</a>
