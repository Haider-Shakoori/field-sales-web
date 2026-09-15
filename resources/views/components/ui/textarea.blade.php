@props([
    'label' => null,
    'name' => null,
    'rows' => 3,
    'hint' => null,
    'required' => false,
    'error' => null,
])

@php
    $error ??= $name ? $errors->first($name) : null;
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'space-y-1.5']) }}>
    @if ($label)
        <x-ui.label :for="$name" :required="$required">{{ $label }}</x-ui.label>
    @endif

    <textarea name="{{ $name }}"
              id="{{ $name }}"
              rows="{{ $rows }}"
              @required($required)
              {{ $attributes->whereStartsWith('x-') }}
              class="surface-input {{ $error ? 'border-red-500 focus:border-red-500 focus:ring-red-500/40 dark:focus:border-red-400 dark:focus:ring-red-400/30' : '' }}">{{ $slot }}</textarea>

    @if ($hint && ! $error)
        <p class="text-xs text-gray-400 dark:text-gray-500">{{ $hint }}</p>
    @endif

    @if ($error)
        <p class="text-xs font-medium text-red-600 dark:text-red-400">{{ $error }}</p>
    @endif
</div>