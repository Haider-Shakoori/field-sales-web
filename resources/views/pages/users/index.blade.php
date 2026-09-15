@extends('layouts.app')

@section('title', 'Users')

@section('content')
    <x-ui.page-header title="Users"
                      description="Members of your organisation and their roles.">
        @if (auth()->user()->hasPermission('users:manage'))
            <x-slot:actions>
                <x-ui.button href="{{ route('users.create') }}" icon="plus">Add user</x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    @if ($branches->isNotEmpty())
        <form method="GET" action="{{ route('users.index') }}" class="surface-card p-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-ui.input name="search" label="Search" :value="request('search')" placeholder="Name or email" />
                <x-ui.select name="role" label="Role" :value="request('role')" :options="['' => 'Any role'] + ($roles ?? [])" />
                <x-ui.select name="branch" label="Branch" :value="request('branch')" :options="['' => 'Any branch'] + $branches->mapWithKeys(fn ($b) => [$b->id => $b->name])->all()" />
                <x-ui.select name="status" label="Status" :value="request('status')" :options="['' => 'Any status', 'active' => 'Active', 'inactive' => 'Inactive']" />
            </div>
            <div class="mt-4 flex items-center justify-end gap-2">
                <x-ui.button href="{{ route('users.index') }}" variant="ghost" type="button">Clear</x-ui.button>
                <x-ui.button type="submit" icon="search">Filter</x-ui.button>
            </div>
        </form>
    @endif

    <x-ui.card>
        @if ($users->isEmpty())
            <x-ui.empty-state title="No users"
                              icon="users"
                              description="Add the first member of this organisation to get started.">
                @if (auth()->user()->hasPermission('users:manage'))
                    <x-slot:action>
                        <x-ui.button href="{{ route('users.create') }}" variant="primary" icon="plus">Add user</x-ui.button>
                    </x-slot:action>
                @endif
            </x-ui.empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-400 dark:border-gray-700 dark:text-gray-500">
                            <th class="px-3 py-3 font-semibold">Name</th>
                            <th class="px-3 py-3 font-semibold">Email</th>
                            <th class="px-3 py-3 font-semibold">Role</th>
                            <th class="px-3 py-3 font-semibold">Branch</th>
                            <th class="px-3 py-3 font-semibold">Status</th>
                            <th class="px-3 py-3 font-semibold">Last login</th>
                            <th class="px-3 py-3 text-right font-semibold"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($users as $user)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                <td class="px-3 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-semibold text-blue-700 dark:bg-blue-500/20 dark:text-blue-300">
                                            {{ strtoupper(substr($user->name, 0, 1)) }}
                                        </span>
                                        <span class="font-medium text-gray-900 dark:text-gray-50">{{ $user->name }}</span>
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $user->email }}</td>
                                <td class="px-3 py-3">
                                    <x-ui.status-badge status="role" :label="ucfirst(str_replace('_', ' ', $user->role))" />
                                </td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $user->branch?->name ?? '—' }}</td>
                                <td class="px-3 py-3">
                                    <x-ui.status-badge :status="$user->is_active ? 'active' : 'inactive'" :label="$user->is_active ? 'Active' : 'Inactive'" />
                                </td>
                                <td class="px-3 py-3 text-gray-400 dark:text-gray-500">
                                    {{ $user->last_login_at?->diffForHumans() ?? 'Never' }}
                                </td>
                                <td class="px-3 py-3 text-right">
                                    <a href="{{ route('users.show', $user) }}"
                                       class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-500/10">
                                        <x-ui.icon name="eye" class="h-3.5 w-3.5" />
                                        View
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.pagination :paginator="$users" />
        @endif
    </x-ui.card>
@endsection