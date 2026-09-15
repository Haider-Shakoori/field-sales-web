@props(['type' => 'info'])

@php
    $styles = [
        'success' => 'border-green-200 bg-green-50 text-green-800 dark:border-green-500/30 dark:bg-green-500/10 dark:text-green-300',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300',
        'danger' => 'border-red-200 bg-red-50 text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300',
        'info' => 'border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-500/30 dark:bg-blue-500/10 dark:text-blue-300',
        'neutral' => 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300',
    ];

    $icons = [
        'success' => 'check',
        'warning' => 'x',
        'danger' => 'x',
        'info' => 'dashboard',
        'neutral' => null,
    ];
@endphp

<div {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded-lg border px-4 py-3 text-sm '.$styles[$type]]) }}>
    @if ($icons[$type])
        <x-ui.icon :name="$icons[$type]" class="mt-0.5 h-4 w-4 shrink-0" />
    @endif
    <div class="flex-1">{{ $slot }}</div>
</div>