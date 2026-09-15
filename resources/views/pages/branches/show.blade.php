@extends('layouts.app')

@section('title', $branch->name)

@section('content')
    <x-ui.page-header :title="$branch->name"
                      :description="'Branch '.$branch->code">
        <x-slot:actions>
            <x-ui.button href="{{ route('branches.index') }}" variant="ghost" icon="branches">All branches</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-ui.card title="Branch overview" class="xl:col-span-1">
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-400 dark:text-gray-500">Name</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-50">{{ $branch->name }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-400 dark:text-gray-500">Code</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-50">{{ $branch->code }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-400 dark:text-gray-500">Status</dt>
                    <dd>
                        <x-ui.status-badge :status="$branch->is_active ? 'active' : 'inactive'" :label="$branch->is_active ? 'Active' : 'Inactive'" />
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-400 dark:text-gray-500">City</dt>
                    <dd class="text-gray-700 dark:text-gray-300">{{ $branch->city ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-400 dark:text-gray-500">Province</dt>
                    <dd class="text-gray-700 dark:text-gray-300">{{ $branch->province ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-400 dark:text-gray-500">Phone</dt>
                    <dd class="text-gray-700 dark:text-gray-300">{{ $branch->phone ?? '—' }}</dd>
                </div>
                @if ($branch->latitude)
                    <div class="flex justify-between">
                        <dt class="text-gray-400 dark:text-gray-500">Coordinates</dt>
                        <dd class="text-gray-700 dark:text-gray-300">
                            {{ $branch->latitude }}, {{ $branch->longitude }}
                        </dd>
                    </div>
                @endif
            </dl>
        </x-ui.card>

        @can('update', $branch)
            <form method="POST" action="{{ route('branches.update', $branch) }}" class="xl:col-span-2">
                @csrf
                @method('PUT')

                <x-ui.card title="Edit branch">
                    <div class="space-y-5">
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <x-ui.input name="name" label="Branch name" :value="old('name', $branch->name)" required />
                            <x-ui.input name="code" label="Branch code" :value="old('code', $branch->code)" required hint="Unique within your organisation." />
                        </div>

                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <x-ui.input name="city" label="City" :value="old('city', $branch->city)" />
                            <x-ui.input name="province" label="Province" :value="old('province', $branch->province)" />
                        </div>

                        <x-ui.textarea name="address" label="Street address" :rows="3">{{ old('address', $branch->address) }}</x-ui.textarea>

                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                            <x-ui.input name="phone" label="Phone" :value="old('phone', $branch->phone)" />
                            <x-ui.input name="latitude" label="Latitude" :value="old('latitude', $branch->latitude)" type="number" step="any" />
                            <x-ui.input name="longitude" label="Longitude" :value="old('longitude', $branch->longitude)" type="number" step="any" />
                        </div>

                        <div>
                            <label class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $branch->is_active))
                                       class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-900">
                                Active
                            </label>
                        </div>
                    </div>

                    <x-slot:footer>
                        <div class="flex items-center justify-end gap-3">
                            <x-ui.button href="{{ route('branches.index') }}" variant="ghost" type="button">Cancel</x-ui.button>
                            <x-ui.button type="submit">Save changes</x-ui.button>
                        </div>
                    </x-slot:footer>
                </x-ui.card>
            </form>
        @endcan

        @can('delete', $branch)
            <div class="xl:col-span-2">
                <x-ui.card title="Danger zone">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Deleting this branch is permanent and cannot be undone.
                        </p>
                        <form method="POST" action="{{ route('branches.destroy', $branch) }}"
                              onsubmit="return confirm('Delete this branch permanently?');">
                            @csrf
                            @method('DELETE')
                            <x-ui.button type="submit" variant="danger" icon="trash">Delete branch</x-ui.button>
                        </form>
                    </div>
                </x-ui.card>
            </div>
        @endcan
    </div>
@endsection