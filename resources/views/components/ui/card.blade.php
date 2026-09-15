@props(['title' => null, 'footer' => null])

<div {{ $attributes->merge(['class' => 'surface-card']) }}>
    @if ($title)
        <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-50">{{ $title }}</h2>
        </div>
    @endif

    <div class="px-5 py-4">{{ $slot }}</div>

    @if ($footer ?? null)
        <div class="border-t border-gray-200 bg-gray-50 px-5 py-3 dark:border-gray-700 dark:bg-gray-800/60">
            {{ $footer }}
        </div>
    @endif
</div>