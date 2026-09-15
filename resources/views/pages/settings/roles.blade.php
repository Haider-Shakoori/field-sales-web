@extends('layouts.app')

@section('title', 'Roles & Permissions')

@section('content')
    <x-ui.page-header title="Roles & Permissions"
                      description="System roles are protected. Create custom roles and assign permissions.">
        @if (auth()->user()->hasPermission('roles:manage'))
            <x-slot:actions>
                <x-ui.button href="{{ route('settings.roles.create') }}" icon="plus">New role</x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <x-ui.card title="Company roles">
        @if ($roles->isEmpty())
            <x-ui.empty-state title="No roles"
                              icon="shield"
                              description="No roles are seeded or available in this tenant.">
                @if (auth()->user()->hasPermission('roles:manage'))
                    <x-slot:action>
                        <x-ui.button href="{{ route('settings.roles.create') }}" variant="primary" icon="plus">New role</x-ui.button>
                    </x-slot:action>
                @endif
            </x-ui.empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-400 dark:border-gray-700 dark:text-gray-500">
                            <th class="px-3 py-3 font-semibold">Role</th>
                            <th class="px-3 py-3 font-semibold">Type</th>
                            <th class="px-3 py-3 font-semibold">Users</th>
                            <th class="px-3 py-3 font-semibold">Permissions</th>
                            <th class="px-3 py-3 text-right font-semibold"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($roles as $entry)
                            @php
                                $role = $entry['role'];
                                $canManage = auth()->user()->can('update', $role);
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                <td class="whitespace-nowrap px-3 py-3 font-medium text-gray-900 dark:text-gray-50">
                                    {{ ucfirst(str_replace('_', ' ', $role->name)) }}
                                </td>
                                <td class="px-3 py-3">
                                    @if ($role->isSystem())
                                        <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500 dark:bg-gray-800 dark:text-gray-400">Protected</span>
                                    @else
                                        <span class="rounded-md bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-500/15 dark:text-blue-300">Custom</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $entry['user_count'] }}</td>
                                <td class="px-3 py-3">
                                    <div class="flex flex-wrap gap-1.5">
                                        @forelse ($role->permissions->take(6) as $permission)
                                            <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                                {{ $permission->name }}
                                            </span>
                                        @empty
                                            <span class="text-xs text-gray-400 dark:text-gray-500">No permissions</span>
                                        @endforelse
                                        @if ($role->permissions->count() > 6)
                                            <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-400 dark:bg-gray-800 dark:text-gray-500">
                                                +{{ $role->permissions->count() - 6 }} more
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-right">
                                    <a href="{{ route('settings.roles.show', $role) }}"
                                       class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-500/10">
                                        <x-ui.icon name="eye" class="h-3.5 w-3.5" />
                                        View
                                    </a>
                                    @if ($canManage)
                                        <a href="{{ route('settings.roles.edit', $role) }}"
                                           class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-500/10">
                                            <x-ui.icon name="pencil" class="h-3.5 w-3.5" />
                                            Edit
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>
@endsection