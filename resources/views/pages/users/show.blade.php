@extends('layouts.app')

@section('title', $user->name)

@section('content')
    <x-ui.page-header :title="$user->name"
                      :description="$user->email">
        <x-slot:actions>
            <x-ui.button href="{{ route('users.index') }}" variant="ghost" icon="users">All users</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-ui.card title="Profile" class="xl:col-span-1">
            <div class="space-y-4">
                <div class="flex items-center gap-4">
                    <span class="flex h-14 w-14 items-center justify-center rounded-full bg-blue-600 text-lg font-bold text-white">
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    </span>
                    <div>
                        <p class="font-semibold text-gray-900 dark:text-gray-50">{{ $user->name }}</p>
                        <p class="text-sm text-gray-400 dark:text-gray-500">Member since {{ $user->created_at?->format('M Y') }}</p>
                    </div>
                </div>

                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-400 dark:text-gray-500">Role</dt>
                        <dd><x-ui.status-badge status="role" :label="ucfirst(str_replace('_', ' ', $user->role))" /></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-400 dark:text-gray-500">Status</dt>
                        <dd>
                            <x-ui.status-badge :status="$user->is_active ? 'active' : 'inactive'" :label="$user->is_active ? 'Active' : 'Inactive'" />
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-400 dark:text-gray-500">Phone</dt>
                        <dd class="text-gray-700 dark:text-gray-300">{{ $user->phone ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-400 dark:text-gray-500">Last login</dt>
                        <dd class="text-gray-700 dark:text-gray-300">{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</dd>
                    </div>
                </dl>
            </div>
        </x-ui.card>

        <div class="space-y-6 xl:col-span-2">
            @can('update', $user)
                <form method="POST" action="{{ route('users.update', $user) }}">
                    @csrf
                    @method('PUT')

                    <x-ui.card title="Edit member">
                        <div class="space-y-5">
                            <x-ui.input name="name" label="Full name" :value="old('name', $user->name)" required />
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                <x-ui.input name="email" type="email" label="Email address" :value="old('email', $user->email)" required />
                                <x-ui.input name="phone" label="Phone" :value="old('phone', $user->phone)" />
                            </div>
                            <x-ui.select name="role"
                                         label="Role"
                                         :value="old('role', $user->role)"
                                         :options="\config('tenancy.roles')"
                                         required />
                        </div>

                        <x-slot:footer>
                            <div class="flex items-center justify-end gap-3">
                                <x-ui.button href="{{ route('users.index') }}" variant="ghost" type="button">Cancel</x-ui.button>
                                <x-ui.button type="submit">Save changes</x-ui.button>
                            </div>
                        </x-slot:footer>
                    </x-ui.card>
                </form>
            @endcan

            @can('deactivate', $user)
                <x-ui.card title="Danger zone">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $user->is_active ? 'Deactivating blocks this member from signing in and using the API.' : 'This member is currently blocked from signing in.' }}
                        </p>
                        <form method="POST" action="{{ route('users.deactivate', $user) }}">
                            @csrf
                            @method('DELETE')
                            <x-ui.button type="submit" variant="danger" icon="power">
                                {{ $user->is_active ? 'Deactivate' : 'Activate' }}
                            </x-ui.button>
                        </form>
                    </div>
                </x-ui.card>
            @endcan
        </div>
    </div>
@endsection