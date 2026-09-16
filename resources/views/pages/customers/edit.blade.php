@extends('layouts.app')

@section('title', 'Edit Customer')

@section('content')
    <x-ui.page-header title="Edit customer"
                      description="Update customer profile for {{ $customer->business_name }}.">

        @if (auth()->user()->hasPermission('customers:deactivate'))
            <x-slot:actions>
                <form method="POST" action="{{ route('customers.deactivate', $customer) }}" class="inline" onsubmit="return confirm('Are you sure?')">
                    @csrf
                    <x-ui.button type="submit" variant="{{ $customer->is_active ? 'danger' : 'success' }}" icon="{{ $customer->is_active ? 'user-minus' : 'user-plus' }}">
                        {{ $customer->is_active ? 'Deactivate' : 'Activate' }}
                    </x-ui.button>
                </form>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <form method="PUT" action="{{ route('customers.update', $customer) }}" class="max-w-4xl space-y-6">
        @csrf
        @method('PUT')

        <x-ui.card title="Basic Information">
            <div class="space-y-5">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-ui.input name="branch_id" type="select" label="Branch" :value="old('branch_id', $customer->branch_id)" :options="$branches->pluck('name', 'id')->all()" required />
                    <x-ui.input name="code" label="Customer Code" :value="old('code', $customer->code)" />
                </div>

                <x-ui.input name="business_name" label="Business Name" :value="old('business_name', $customer->business_name)" required />

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-ui.input name="contact_person" label="Contact Person" :value="old('contact_person', $customer->contact_person)" />
                    <x-ui.input name="phone" label="Phone" :value="old('phone', $customer->phone)" placeholder="+93 ..." />
                </div>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-ui.input name="whatsapp" label="WhatsApp" :value="old('whatsapp', $customer->whatsapp)" placeholder="+93 ..." />
                    <x-ui.select name="category_id" label="Category" :value="old('category_id', $customer->category_id)" :options="['' => 'Select category'] + $categories->pluck('name', 'id')->all()" />
                </div>
            </div>

            <x-slot:footer>
                <div class="flex items-center justify-end gap-3">
                    <x-ui.button href="{{ route('customers.show', $customer) }}" variant="ghost" type="button">Cancel</x-ui.button>
                    <x-ui.button type="submit">Save changes</x-ui.button>
                </div>
            </x-slot:footer>
        </x-ui.card>

        <x-ui.card title="Location">
            <div class="space-y-5">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                    <x-ui.input name="province" label="Province" :value="old('province', $customer->province)" />
                    <x-ui.input name="district" label="District" :value="old('district', $customer->district)" />
                    <x-ui.input name="address" label="Address" :value="old('address', $customer->address)" placeholder="Street address" />
                </div>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                    <x-ui.input name="latitude" type="number" step="0.0000001" label="Latitude" :value="old('latitude', $customer->latitude)" placeholder="e.g. 34.5553" />
                    <x-ui.input name="longitude" type="number" step="0.0000001" label="Longitude" :value="old('longitude', $customer->longitude)" placeholder="e.g. 69.2075" />
                    <x-ui.input name="geofence_radius" type="number" label="Geofence Radius (m)" :value="old('geofence_radius', $customer->geofence_radius)" min="1" max="5000" />
                </div>
            </div>
        </x-ui.card>

        <x-ui.card title="Assignment">
            <div class="space-y-5">
                <x-ui.select name="territory_id" label="Territory" :value="old('territory_id', $customer->territory_id)" :options="$territories->pluck('name', 'id')->all()" required />

                <x-ui.select name="route_id" label="Route" :value="old('route_id', $customer->route_id)" :options="['' => 'No route'] + $routes->pluck('name', 'id')->all()" />

                <x-ui.select name="assigned_salesman_id" label="Assigned Salesman" :value="old('assigned_salesman_id', $customer->assigned_salesman_id)" :options="['' => 'Unassigned'] + $salesmen->mapWithKeys(fn ($s) => [$s->id => trim($s->first_name.' '.$s->last_name).' ('.$s->employee_code.')'])->all()" />
            </div>
        </x-ui.card>

        <x-ui.card title="Commercial">
            <div class="space-y-5">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-ui.input name="credit_limit" type="number" step="0.01" label="Credit Limit" :value="old('credit_limit', $customer->credit_limit)" min="0" />
                    <x-ui.input name="outstanding_balance" type="number" step="0.01" label="Outstanding Balance" :value="old('outstanding_balance', $customer->outstanding_balance)" min="0" />
                </div>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-ui.select name="price_list_id" label="Price List" :value="old('price_list_id', $customer->price_list_id)" :options="['' => 'Default']" />
                    <x-ui.select name="visit_frequency" label="Visit Frequency" :value="old('visit_frequency', $customer->visit_frequency)" :options="['' => 'Not set', 'daily' => 'Daily', 'weekly' => 'Weekly', 'biweekly' => 'Bi-weekly', 'monthly' => 'Monthly']" />
                </div>
            </div>
        </x-ui.card>

        <x-ui.card title="Notes">
            <x-ui.textarea name="notes" label="Notes" :value="old('notes', $customer->notes)" rows="3" placeholder="Additional notes about this customer..." />
        </x-ui.card>

        <x-ui.card title="Status">
            <div class="space-y-5">
                <x-ui.select name="is_active" label="Status" :value="old('is_active', $customer->is_active)" :options="[true => 'Active', false => 'Inactive']" />
            </div>
        </x-ui.card>
    </form>
@endsection