@extends('layouts.app')

@section('title', 'Roles & Permissions')

@section('content')
    <x-ui.page-header title="Roles & Permissions"
                      description="Read-only role catalogue. Permission adjustments ship with the RBAC manager in a later batch." />

    <x-ui.card title="Company roles">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-400 dark:border-gray-700 dark:text-gray-500">
                        <th class="px-3 py-3 font-semibold">Role</th>
                        <th class="px-3 py-3 font-semibold">Permissions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($roles as $role)
                        <tr>
                            <td class="whitespace-nowrap px-3 py-3 font-medium text-gray-900 dark:text-gray-50">
                                {{ ucfirst(str_replace('_', ' ', $role->name)) }}
                            </td>
                            <td class="px-3 py-3">
                                <div class="flex flex-wrap gap-1.5">
                                    @forelse ($role->permissions as $permission)
                                        <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                            {{ $permission->name }}
                                        </span>
                                    @empty
                                        <span class="text-xs text-gray-400 dark:text-gray-500">No permissions</span>
                                    @endforelse
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-3 py-6 text-center text-sm text-gray-400 dark:text-gray-500">
                                No roles seeded.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>
@endsection