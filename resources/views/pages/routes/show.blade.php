@extends('layouts.app')

@section('title', 'Route: {{ $route->name }}')

@section('content')
    <x-ui.page-header title="{{ $route->name }}"
                      description="Code: {{ $route->code }} — Territory: {{ $route->territory?->name }}">

        @if (auth()->user()->hasPermission('routes:update'))
            <x-slot:actions>
                <x-ui.button href="{{ route('routes.edit', $route) }}" icon="pencil">Edit</x-ui.button>
            </x-slot:actions>
        @endif

        @if (auth()->user()->hasPermission('routes:deactivate'))
            <x-slot:actions>
                <form method="POST" action="{{ route('routes.deactivate', $route) }}" class="inline" onsubmit="return confirm('Are you sure?')">
                    @csrf
                    <x-ui.button type="submit" variant="{{ $route->is_active ? 'danger' : 'success' }}" icon="{{ $route->is_active ? 'map-pin-off' : 'map-pin' }}">
                        {{ $route->is_active ? 'Deactivate' : 'Activate' }}
                    </x-ui.button>
                </form>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <x-ui.card title="Route Details">
                <dl class="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Code</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50 font-mono">{{ $route->code }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Name</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $route->name }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Territory</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">
                        <a href="{{ route('territories.show', $route->territory) }}" class="text-blue-600 hover:underline">{{ $route->territory?->name ?? '—' }}</a>
                    </dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Branch</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $route->territory?->branch?->name ?? '—' }}</dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Weekday</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50">
                        @if ($route->weekday !== null)
                            {{ ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'][$route->weekday] }}
                        @else
                            Any day
                        @endif
                    </dd>

                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Description</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-50 sm:col-span-2">{{ $route->description ?? '—' }}</dd>
                </dl>
            </x-ui.card>

            <x-ui.card title="Customers ({{ $route->routeCustomers->count() }})">
                @if (auth()->user()->hasPermission('assignments:manage'))
                    <x-slot:header>
                        <div class="flex items-center justify-between">
                            <h3 class="font-semibold text-gray-900 dark:text-gray-50">Customers</h3>
                            <x-ui.button href="#" x-on:click="openAddModal" variant="primary" size="sm" icon="plus">Add Customer</x-ui.button>
                        </div>
                    </x-slot:header>

                    @if ($route->routeCustomers->isEmpty())
                        <p class="text-gray-500 dark:text-gray-400 py-4 text-center">No customers assigned to this route.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-400 dark:border-gray-700 dark:text-gray-500">
                                        <th class="px-3 py-3 font-semibold">#</th>
                                        <th class="px-3 py-3 font-semibold">Code</th>
                                        <th class="px-3 py-3 font-semibold">Business Name</th>
                                        <th class="px-3 py-3 font-semibold">Contact</th>
                                        <th class="px-3 py-3 font-semibold">Effective From</th>
                                        <th class="px-3 py-3 font-semibold">Effective To</th>
                                        <th class="px-3 py-3 font-semibold">Status</th>
                                        <th class="px-3 py-3 text-right font-semibold"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach ($route->routeCustomers->sortBy('visit_order') as $rc)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                            <td class="px-3 py-3 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $rc->visit_order }}</td>
                                            <td class="px-3 py-3 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $rc->customer->code }}</td>
                                            <td class="px-3 py-3 font-medium text-gray-900 dark:text-gray-50">{{ $rc->customer->business_name }}</td>
                                            <td class="px-3 py-3 text-gray-500 dark:text-gray-400">
                                                {{ $rc->customer->contact_person ?? '—' }}<br>
                                                {{ $rc->customer->phone ?? '—' }}
                                            </td>
                                            <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $rc->effective_from?->format('Y-m-d') }}</td>
                                            <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $rc->effective_to?->format('Y-m-d') ?? 'Present' }}</td>
                                            <td class="px-3 py-3">
                                                @if ($rc->isCurrentlyActive())
                                                    <x-ui.status-badge status="active" label="Active" />
                                                @else
                                                    <x-ui.status-badge status="inactive" label="Ended" />
                                                @endif
                                            </td>
                                            <td class="px-3 py-3 text-right">
                                                <div class="flex items-center justify-end gap-1">
                                                    @if (auth()->user()->hasPermission('assignments:manage'))
                                                        <button x-on:click="openEditModal({{ $rc->id }})"
                                                                class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-500/10">
                                                            <x-ui.icon name="pencil" class="h-3.5 w-3.5" />
                                                        </button>
                                                        <form method="POST" action="{{ route('routes.customers.remove', [$route, $rc]) }}" class="inline" onsubmit="return confirm('Remove this customer from the route?')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <x-ui.button type="submit" variant="danger" size="sm" icon="trash" class="p-1" />
                                                        </form>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @else
                    @if ($route->routeCustomers->isEmpty())
                        <p class="text-gray-500 dark:text-gray-400 py-4 text-center">No customers assigned to this route.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-400 dark:border-gray-700 dark:text-gray-500">
                                        <th class="px-3 py-3 font-semibold">#</th>
                                        <th class="px-3 py-3 font-semibold">Code</th>
                                        <th class="px-3 py-3 font-semibold">Business Name</th>
                                        <th class="px-3 py-3 font-semibold">Contact</th>
                                        <th class="px-3 py-3 font-semibold">Effective From</th>
                                        <th class="px-3 py-3 font-semibold">Effective To</th>
                                        <th class="px-3 py-3 font-semibold">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach ($route->routeCustomers->sortBy('visit_order') as $rc)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                            <td class="px-3 py-3 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $rc->visit_order }}</td>
                                            <td class="px-3 py-3 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $rc->customer->code }}</td>
                                            <td class="px-3 py-3 font-medium text-gray-900 dark:text-gray-50">{{ $rc->customer->business_name }}</td>
                                            <td class="px-3 py-3 text-gray-500 dark:text-gray-400">
                                                {{ $rc->customer->contact_person ?? '—' }}<br>
                                                {{ $rc->customer->phone ?? '—' }}
                                            </td>
                                            <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $rc->effective_from?->format('Y-m-d') }}</td>
                                            <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $rc->effective_to?->format('Y-m-d') ?? 'Present' }}</td>
                                            <td class="px-3 py-3">
                                                @if ($rc->isCurrentlyActive())
                                                    <x-ui.status-badge status="active" label="Active" />
                                                @else
                                                    <x-ui.status-badge status="inactive" label="Ended" />
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endif
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <x-ui.card title="Status">
                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
                        <dd class="text-sm text-gray-900 dark:text-gray-50">
                            <x-ui.status-badge :status="$route->is_active ? 'active' : 'inactive'" :label="$route->is_active ? 'Active' : 'Inactive'" />
                        </dd>
                    </div>

                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created</dt>
                        <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $route->created_at->format('Y-m-d H:i:s') }}</dd>
                    </div>

                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Updated</dt>
                        <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $route->updated_at->format('Y-m-d H:i:s') }}</dd>
                    </div>

                    @if ($route->deleted_at)
                        <div class="flex justify-between">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Deactivated</dt>
                            <dd class="text-sm text-gray-900 dark:text-gray-50">{{ $route->deleted_at->format('Y-m-d H:i:s') }}</dd>
                        </div>
                    @endif
                </dl>
            </x-ui.card>
        </div>
    </div>

    <!-- Add/Edit Customer Modal -->
    @if (auth()->user()->hasPermission('assignments:manage'))
        <div x-data="routeCustomerModal({ routeId: {{ $route->id }}, territories: @json($route->territory ? [$route->territory->id => $route->territory->name] : []) })"
             x-show="isOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 overflow-y-auto hidden"
             role="dialog"
             aria-modal="true"
             aria-labelledby="modal-title">
            <div class="flex min-h-full items-center justify-center p-4">
                <div x-show="isOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="relative w-full max-w-lg bg-white dark:bg-gray-900 rounded-xl shadow-xl">
                    <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 px-6 py-4">
                        <h3 x-text="editing ? 'Edit Route Customer' : 'Add Customer to Route'" id="modal-title" class="text-lg font-semibold text-gray-900 dark:text-gray-50"></h3>
                        <button x-on:click="close" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <x-ui.icon name="x" class="h-5 w-5" />
                        </button>
                    </div>

                    <form x-on:submit.prevent="submit" class="p-6 space-y-4">
                        <input type="hidden" name="route_customer_id" x-model="form.route_customer_id">

                        <div>
                            <x-ui.label for="customer_id">Customer</x-ui.label>
                            <x-ui.select id="customer_id" name="customer_id" x-model="form.customer_id" :options="customers" required />
                            <p x-show="errors.customer_id" class="text-red-500 text-sm mt-1" x-text="errors.customer_id"></p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-ui.label for="visit_order">Visit Order</x-ui.label>
                                <x-ui.input id="visit_order" type="number" name="visit_order" x-model.number="form.visit_order" min="1" required />
                                <p x-show="errors.visit_order" class="text-red-500 text-sm mt-1" x-text="errors.visit_order"></p>
                            </div>

                            <div>
                                <x-ui.label for="effective_from">Effective From</x-ui.label>
                                <x-ui.input id="effective_from" type="date" name="effective_from" x-model="form.effective_from" required />
                                <p x-show="errors.effective_from" class="text-red-500 text-sm mt-1" x-text="errors.effective_from"></p>
                            </div>
                        </div>

                        <div>
                            <x-ui.label for="effective_to">Effective To (optional)</x-ui.label>
                            <x-ui.input id="effective_to" type="date" name="effective_to" x-model="form.effective_to" />
                            <p x-show="errors.effective_to" class="text-red-500 text-sm mt-1" x-text="errors.effective_to"></p>
                        </div>

                        <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <x-ui.button type="button" variant="ghost" x-on:click="close">Cancel</x-ui.button>
                            <x-ui.button type="submit" x-text="editing ? 'Update' : 'Add'" />
                        </div>
                    </form>
                </div>
            </div>

            <div x-show="isOpen" x-transition:enter="ease-linear duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-linear duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-black/50"></div>
        </div>
    @endif

    <script>
        function routeCustomerModal({ routeId, territories }) {
            return {
                isOpen: false,
                editing: false,
                form: {
                    route_customer_id: null,
                    customer_id: '',
                    visit_order: 1,
                    effective_from: '',
                    effective_to: '',
                },
                errors: {},
                customers: [],

                openAddModal() {
                    this.editing = false;
                    this.form = {
                        route_customer_id: null,
                        customer_id: '',
                        visit_order: this.getNextVisitOrder(),
                        effective_from: new Date().toISOString().split('T')[0],
                        effective_to: '',
                    };
                    this.loadCustomers();
                    this.isOpen = true;
                },

                openEditModal(id) {
                    this.editing = true;
                    this.isOpen = true;
                    this.loadCustomers().then(() => {
                        const rc = this.getRouteCustomer(id);
                        if (rc) {
                            this.form = {
                                route_customer_id: rc.id,
                                customer_id: rc.customer_id,
                                visit_order: rc.visit_order,
                                effective_from: rc.effective_from,
                                effective_to: rc.effective_to || '',
                            };
                        }
                    });
                },

                getRouteCustomer(id) {
                    // We'd need to fetch this - for now just use a simple approach
                    return null;
                },

                getNextVisitOrder() {
                    const orders = @json($route->routeCustomers->pluck('visit_order')->all());
                    return orders.length > 0 ? Math.max(...orders) + 1 : 1;
                },

                async loadCustomers() {
                    try {
                        const response = await fetch(`/api/admin/routes/${routeId}/available-customers`);
                        this.customers = await response.json();
                    } catch (e) {
                        this.customers = [];
                    }
                },

                close() {
                    this.isOpen = false;
                    this.errors = {};
                },

                async submit() {
                    this.errors = {};
                    const url = this.editing
                        ? `/admin/routes/${routeId}/customers/${this.form.route_customer_id}`
                        : `/admin/routes/${routeId}/customers`;
                    const method = this.editing ? 'PUT' : 'POST';

                    try {
                        const response = await fetch(url, {
                            method,
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify(this.form),
                        });

                        if (response.ok) {
                            window.location.reload();
                        } else {
                            const data = await response.json();
                            if (data.errors) {
                                this.errors = data.errors;
                            }
                        }
                    } catch (e) {
                        console.error(e);
                    }
                },
            };
        }
    </script>
@endsection