@props([
    'title' => '',
    'description' => '',
    'actions' => null,
])

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-gray-50">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
        @endif
    </div>
    @if ($actions ?? null)
        <div class="flex shrink-0 items-center gap-3">
            {{ $actions }}
        </div>
    @endif
</div>