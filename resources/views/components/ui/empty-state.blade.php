@props([
    'title' => 'Nothing here yet',
    'description' => null,
    'icon' => 'search',
    'action' => null,
    'bordered' => true,
])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-10 text-center '.($bordered ? 'rounded-xl border border-dashed border-gray-300 dark:border-gray-700' : '')]) }}>
    <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500">
        <x-ui.icon :name="$icon" class="h-6 w-6" />
    </div>
    <p class="text-sm font-semibold text-gray-900 dark:text-gray-50">{{ $title }}</p>
    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
    @endif
    @if ($action ?? null)
        <div class="mt-5">{{ $action }}</div>
    @endif
</div>
