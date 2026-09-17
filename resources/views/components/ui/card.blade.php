@props(['title' => null, 'footer' => null])

<div {{ $attributes->merge(['class' => 'surface-card']) }}>
    @if ($title || isset($actions))
        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-700/60">
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-50">{{ $title }}</h2>

            @isset($actions)
                <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <div class="px-5 py-4">{{ $slot }}</div>

    @if ($footer ?? null)
        <div class="border-t border-gray-100 bg-gray-50/70 px-5 py-3 dark:border-gray-700/60 dark:bg-gray-800/60">
            {{ $footer }}
        </div>
    @endif
</div>
