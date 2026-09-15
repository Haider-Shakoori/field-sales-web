@props([
    'matrix' => [],
    'selected' => [],
    'editable' => false,
    'disabled' => false,
    'name' => 'permissions',
])

@php
    $selected = collect($selected)->map(fn ($id) => (int) $id)->all();
    $sensitive = config('tenancy.sensitive_permissions', ['users:manage', 'roles:manage', 'settings:manage']);
@endphp

<div class="overflow-x-auto">
    <table class="w-full text-left text-sm">
        <thead>
            <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-400 dark:border-gray-700 dark:text-gray-500">
                <th class="px-3 py-3 font-semibold">Resource</th>
                <th class="px-3 py-3 font-semibold">Permissions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @forelse ($matrix as $group)
                <tr>
                    <td class="whitespace-nowrap px-3 py-3 font-medium text-gray-900 dark:text-gray-50">
                        {{ ucfirst($group['resource']) }}
                    </td>
                    <td class="px-3 py-3">
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($group['actions'] as $action => $permissionId)
                                @php
                                    $checked = $permissionId !== null && in_array((int) $permissionId, $selected, true);
                                    $isSensitive = $permissionId !== null
                                        && in_array($group['resource'].':'.$action, $sensitive, true);
                                @endphp
                                <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-600 transition-colors dark:border-gray-700 dark:text-gray-300 {{ $checked ? 'border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-500/40 dark:bg-blue-500/15 dark:text-blue-300' : 'hover:bg-gray-50 dark:hover:bg-gray-800/60' }}">
                                    @if ($editable)
                                        <input type="checkbox"
                                               name="{{ $name }}[]"
                                               value="{{ $permissionId }}"
                                               @checked($checked)
                                               @disabled($disabled)
                                               class="h-3.5 w-3.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800"
                                               @if ($isSensitive) data-sensitive="true" @endif>
                                    @else
                                        <x-ui.icon :name="$checked ? 'check' : 'x'" class="h-3.5 w-3.5 {{ $checked ? 'text-blue-600 dark:text-blue-400' : 'text-gray-300 dark:text-gray-600' }}" />
                                    @endif
                                    {{ $action }}
                                </label>
                            @endforeach
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="px-3 py-6 text-center text-sm text-gray-400 dark:text-gray-500">
                        No permissions seeded.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>