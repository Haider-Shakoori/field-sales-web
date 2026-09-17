@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'submit',
    'icon' => null,
])

@php
    $variants = [
        'primary' => 'bg-blue-600 text-white shadow-sm hover:bg-blue-500 active:bg-blue-700 focus-visible:outline-blue-600 disabled:hover:bg-blue-600',
        'secondary' => 'bg-white text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 active:bg-gray-100 disabled:hover:bg-white dark:bg-gray-800 dark:text-gray-100 dark:ring-gray-600 dark:hover:bg-gray-700 dark:active:bg-gray-700 dark:disabled:hover:bg-gray-800',
        'ghost' => 'text-gray-700 hover:bg-gray-100 active:bg-gray-200 disabled:hover:bg-transparent dark:text-gray-300 dark:hover:bg-gray-700 dark:active:bg-gray-700',
        'danger' => 'bg-red-600 text-white shadow-sm hover:bg-red-500 active:bg-red-700 focus-visible:outline-red-600 disabled:hover:bg-red-600',
    ];

    $sizes = [
        'sm' => 'px-2.5 py-1.5 text-xs',
        'md' => 'px-3.5 py-2 text-sm',
        'lg' => 'px-4 py-2.5 text-sm',
    ];

    $classes = 'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-lg font-semibold transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-60 '.$variants[$variant].' '.$sizes[$size];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)
            <x-ui.icon :name="$icon" class="h-4 w-4" />
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)
            <x-ui.icon :name="$icon" class="h-4 w-4" />
        @endif
        {{ $slot }}
    </button>
@endif