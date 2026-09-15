@props(['title' => 'Nothing here yet', 'description' => null, 'icon' => 'search', 'action' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center rounded-lg border border-dashed border-gray-300 px-6 py-12 text-center dark:border-gray-700']) }}>
    <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500">
        <x-ui.icon :name="$icon" class="h-6 w-6" />
    </div>
    <p class="text-sm font-semibold text-gray-900 dark:text-gray-50">{{ $title }}</p>
    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
    @endif
    @if ($action ?? null)
        <div class="mt-4">{{ $action }}</div>
    @endif
</div>