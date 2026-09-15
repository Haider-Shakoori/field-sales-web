@extends('layouts.app')

@section('title', 'New Role')

@section('content')
    <x-ui.page-header title="New role"
                      description="Create a custom company role and grant permissions." />

    <form method="POST" action="{{ route('settings.roles.store') }}" class="max-w-3xl space-y-6"
          x-data="permissionForm()"
          x-on:submit.prevent="submit($el)">
        @csrf

        <x-ui.card title="Role">
            <x-ui.input name="name"
                        label="Role name"
                        :value="old('name')"
                        required
                        autofocus
                        placeholder="e.g. operations_manager"
                        hint="Lowercase, underscores only. Must not shadow a system role." />

            <div class="mt-5">
                <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Permissions</p>
                <x-ui.permission-matrix :matrix="$matrix" :selected="old('permissions', [])" editable name="permissions" />
            </div>

            <x-slot:footer>
                <div class="flex items-center justify-end gap-3">
                    <x-ui.button href="{{ route('settings.roles.index') }}" variant="ghost" type="button">Cancel</x-ui.button>
                    <x-ui.button type="submit">Create role</x-ui.button>
                </div>
            </x-slot:footer>
        </x-ui.card>

        <div x-show="confirmSensitive" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4"
             x-transition.opacity>
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-gray-800">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-50">Confirm sensitive permissions</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    The selected role grants one or more sensitive permissions
                    (<span class="font-medium text-gray-700 dark:text-gray-300" x-text="sensitiveNames.join(', ')"></span>).
                    These grant elevated control. Confirm the assignment?
                </p>
                <div class="mt-5 flex items-center justify-end gap-3">
                    <x-ui.button type="button" variant="ghost" x-on:click="confirmSensitive = false">Cancel</x-ui.button>
                    <x-ui.button type="button" variant="primary" x-on:click="confirmSensitive = false; $el.closest('form').submit()">Confirm</x-ui.button>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        function permissionForm() {
            return {
                confirmSensitive: false,
                sensitiveNames: [],

                submit(form) {
                    const selected = Array.from(form.querySelectorAll('input[name="permissions[]"]:checked'))
                        .map((input) => input.value);
                    this.sensitiveNames = Array.from(form.querySelectorAll('input[data-sensitive]'))
                        .filter((input) => input.checked)
                        .map((input) => {
                            const label = input.closest('label');
                            return label ? label.textContent.trim() : input.value;
                        });

                    if (this.sensitiveNames.length > 0) {
                        this.confirmSensitive = true;
                        return;
                    }

                    form.submit();
                },
            };
        }
    </script>
@endpush