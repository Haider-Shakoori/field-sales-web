@props([
    'title' => '',
    'description' => '',
    'actions' => null,
])

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <div class="min-w-0">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl dark:text-gray-50">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
        @endif
    </div>
    @if ($actions ?? null)
        <div class="flex shrink-0 flex-wrap items-center gap-3">
            {{ $actions }}
        </div>
    @endif
</div>
