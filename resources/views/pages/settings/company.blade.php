@extends('layouts.app')

@section('title', 'Company Settings')

@section('content')
    <x-ui.page-header title="Company Settings"
                      description="Profile details are stored on the tenant record and scoped to your organisation." />

    <form method="POST" action="{{ route('settings.company.update') }}" class="max-w-2xl space-y-6">
        @csrf
        @method('PUT')

        <x-ui.card title="Company profile">
            <div class="space-y-5">
                <x-ui.input name="name" label="Company name" :value="old('name', $tenant->name)" required />

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-ui.select name="timezone"
                                 label="Timezone"
                                 :value="old('timezone', $tenant->timezone)"
                                 :options="$timezones"
                                 required />
                    <x-ui.select name="locale"
                                 label="Language"
                                 :value="old('locale', $tenant->locale)"
                                 :options="$locales"
                                 required />
                </div>

                <x-ui.select name="default_currency"
                             label="Default currency"
                             :value="old('default_currency', $tenant->default_currency)"
                             :options="$currencies->pluck('code', 'code')->map(fn ($code) => $code.' · '.$currencies->firstWhere('code', $code)?->name)->all()"
                             required />
            </div>

            <x-slot:footer>
                <div class="flex items-center justify-end gap-3">
                    <x-ui.button href="{{ route('dashboard') }}" variant="ghost" type="button">Cancel</x-ui.button>
                    <x-ui.button type="submit">Save changes</x-ui.button>
                </div>
            </x-slot:footer>
        </x-ui.card>
    </form>
@endsection