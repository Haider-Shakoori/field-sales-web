@php
    $assignment = $assignment ?? null;
    $isEdit = $assignment !== null;
@endphp

<form method="POST"
      action="{{ $isEdit ? route('salesman-assignments.update', $assignment) : route('salesman-assignments.store') }}"
      class="max-w-4xl space-y-6">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <x-ui.card title="Assignment">
        <div class="space-y-5">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <x-ui.select name="salesman_id"
                             label="Salesman"
                             :value="old('salesman_id', $assignment->salesman_id ?? null)"
                             :options="['' => 'Select salesman'] + $salesmen->mapWithKeys(fn ($s) => [$s->id => trim($s->first_name.' '.$s->last_name).' ('.$s->employee_code.')'])->all()"
                             required />
                <x-ui.select name="supervisor_id"
                             label="Supervisor"
                             :value="old('supervisor_id', $assignment->supervisor_id ?? null)"
                             :options="['' => 'No supervisor'] + $supervisors->mapWithKeys(fn ($s) => [$s->id => trim($s->first_name.' '.$s->last_name).' ('.$s->employee_code.')'])->all()" />
            </div>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                <x-ui.select name="branch_id"
                             label="Branch"
                             :value="old('branch_id', $assignment->branch_id ?? null)"
                             :options="['' => 'Select branch'] + $branches->pluck('name', 'id')->all()"
                             required />
                <x-ui.select name="territory_id"
                             label="Territory"
                             :value="old('territory_id', $assignment->territory_id ?? null)"
                             :options="['' => 'Select territory'] + $territories->pluck('name', 'id')->all()"
                             required />
                <x-ui.select name="route_id"
                             label="Route"
                             :value="old('route_id', $assignment->route_id ?? null)"
                             :options="['' => 'No route'] + $routes->pluck('name', 'id')->all()" />
            </div>
        </div>
    </x-ui.card>

    <x-ui.card title="Effective period">
        <div class="space-y-5">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <x-ui.input name="effective_from"
                            type="date"
                            label="Effective from"
                            :value="old('effective_from', $isEdit ? $assignment->effective_from?->format('Y-m-d') : now()->toDateString())"
                            required />
                <x-ui.input name="effective_to"
                            type="date"
                            label="Effective to"
                            :value="old('effective_to', $isEdit ? $assignment->effective_to?->format('Y-m-d') : null)"
                            hint="Leave blank for an ongoing assignment." />
            </div>
        </div>

        <x-slot:footer>
            <div class="flex items-center justify-end gap-3">
                <x-ui.button href="{{ $isEdit ? route('salesman-assignments.show', $assignment) : route('salesman-assignments.index') }}"
                             variant="ghost"
                             type="button">Cancel</x-ui.button>
                <x-ui.button type="submit">{{ $isEdit ? 'Save changes' : 'Create assignment' }}</x-ui.button>
            </div>
        </x-slot:footer>
    </x-ui.card>
</form>
