@props([
    'name' => null,
    'label' => null,
    'checked' => false,
    'hint' => null,
    'disabled' => false,
])

<div class="flex items-start justify-between gap-4">
    <div class="min-w-0">
        @if ($label)
            <x-ui.label :for="$name">{{ $label }}</x-ui.label>
        @endif

        @if ($hint)
            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ $hint }}</p>
        @endif
    </div>

    <label class="relative inline-flex shrink-0 cursor-pointer items-center">
        <input type="hidden" name="{{ $name }}" value="0">
        <input type="checkbox"
               id="{{ $name }}"
               name="{{ $name }}"
               value="1"
               role="switch"
               @checked((bool) old($name, $checked))
               @disabled($disabled)
               class="peer sr-only">
        <span class="h-6 w-11 rounded-full bg-gray-200 transition-colors peer-checked:bg-blue-600 peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-blue-500 dark:bg-gray-700"></span>
        <span class="absolute start-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform peer-checked:translate-x-5 rtl:peer-checked:-translate-x-5"></span>
    </label>
</div>
