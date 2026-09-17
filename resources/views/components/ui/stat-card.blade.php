@props(['label' => '', 'value' => null, 'description' => null, 'icon' => null, 'tone' => 'blue'])

@php
    $tones = [
        'blue' => 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400',
        'green' => 'bg-green-50 text-green-600 dark:bg-green-500/10 dark:text-green-400',
        'amber' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400',
        'gray' => 'bg-gray-100 text-gray-500 dark:bg-gray-700/40 dark:text-gray-400',
    ];

    $toneClasses = $tones[$tone] ?? $tones['blue'];
@endphp

<div {{ $attributes->merge(['class' => 'surface-card p-5']) }}>
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <p class="truncate text-sm font-medium text-gray-500 dark:text-gray-400">{{ $label }}</p>
            <p class="mt-2 text-3xl font-semibold tracking-tight text-gray-900 dark:text-gray-50">{{ $value }}</p>
            @if ($description)
                <p class="mt-1.5 text-xs text-gray-400 dark:text-gray-500">{{ $description }}</p>
            @endif
        </div>

        @if ($icon)
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $toneClasses }}">
                <x-ui.icon :name="$icon" class="h-5 w-5" />
            </span>
        @endif
    </div>
</div>
