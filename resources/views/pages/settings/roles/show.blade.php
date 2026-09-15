@extends('layouts.app')

@section('title', ucfirst(str_replace('_', ' ', $role->name)))

@section('content')
    <x-ui.page-header :title="ucfirst(str_replace('_', ' ', $role->name))"
                      :description="'Role permissions for '.$role->name">
        <x-slot:actions>
            <x-ui.button href="{{ route('settings.roles.index') }}" variant="ghost" icon="shield">All roles</x-ui.button>
            @if (auth()->user()->can('update', $role))
                <x-ui.button href="{{ route('settings.roles.edit', $role) }}" icon="pencil">Edit</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-ui.card title="Details" class="xl:col-span-1">
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-400 dark:text-gray-500">Slug</dt>
                    <dd class="font-medium text-gray-700 dark:text-gray-300">{{ $role->name }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-400 dark:text-gray-500">Type</dt>
                    <dd>
                        @if ($role->isSystem())
                            <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500 dark:bg-gray-800 dark:text-gray-400">Protected system role</span>
                        @else
                            <span class="rounded-md bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-500/15 dark:text-blue-300">Tenant custom role</span>
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-400 dark:text-gray-500">Assigned users</dt>
                    <dd class="font-medium text-gray-700 dark:text-gray-300">{{ $role->userCount() }}</dd>
                </div>
            </dl>
        </x-ui.card>

        <x-ui.card title="Permission matrix" class="xl:col-span-2">
            <x-ui.permission-matrix :matrix="$matrix" :selected="$role->permissions->pluck('id')->all()" />
        </x-ui.card>
    </div>
@endsection