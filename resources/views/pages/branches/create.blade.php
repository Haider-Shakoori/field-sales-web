@extends('layouts.app')

@section('title', 'Add Branch')

@section('content')
    <x-ui.page-header title="Add branch"
                      description="Create a physical branch under your organisation." />

    <form method="POST" action="{{ route('branches.store') }}" class="max-w-2xl space-y-6">
        @csrf

        <x-ui.card title="Branch details">
            <div class="space-y-5">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-ui.input name="name" label="Branch name" :value="old('name')" required autofocus placeholder="e.g. Jalalabad Warehouse" />
                    <x-ui.input name="code" label="Branch code" :value="old('code')" required placeholder="e.g. JBD-02" hint="Unique within your organisation." />
                </div>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-ui.input name="city" label="City" :value="old('city')" placeholder="e.g. Jalalabad" />
                    <x-ui.input name="province" label="Province" :value="old('province')" placeholder="e.g. Nangarhar" />
                </div>

                <x-ui.textarea name="address" label="Street address" :rows="3">{{ old('address') }}</x-ui.textarea>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                    <x-ui.input name="phone" label="Phone" :value="old('phone')" placeholder="+93 ..." />
                    <x-ui.input name="latitude" label="Latitude" :value="old('latitude')" type="number" step="any" placeholder="34.530911" />
                    <x-ui.input name="longitude" label="Longitude" :value="old('longitude')" type="number" step="any" placeholder="69.136758" />
                </div>

                <div>
                    <label class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))
                               class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-900">
                        Active
                    </label>
                </div>
            </div>

            <x-slot:footer>
                <div class="flex items-center justify-end gap-3">
                    <x-ui.button href="{{ route('branches.index') }}" variant="ghost" type="button">Cancel</x-ui.button>
                    <x-ui.button type="submit">Create branch</x-ui.button>
                </div>
            </x-slot:footer>
        </x-ui.card>
    </form>
@endsection