@extends('layouts.app')

@section('title', 'Branches')

@section('content')
    <x-ui.page-header title="Branches"
                      description="Physical points of sale and offices for your organisation.">
        @if (auth()->user()->hasPermission('branches:manage'))
            <x-slot:actions>
                <x-ui.button href="{{ route('branches.create') }}" icon="plus">Add branch</x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <x-ui.card>
        @if ($branches->isEmpty())
            <x-ui.empty-state title="No branches"
                              icon="building"
                              description="Create your first branch to begin planning territories and visits (Batch 2).">
                @if (auth()->user()->hasPermission('branches:manage'))
                    <x-slot:action>
                        <x-ui.button href="{{ route('branches.create') }}" variant="primary" icon="plus">Add branch</x-ui.button>
                    </x-slot:action>
                @endif
            </x-ui.empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-400 dark:border-gray-700 dark:text-gray-500">
                            <th class="px-3 py-3 font-semibold">Branch</th>
                            <th class="px-3 py-3 font-semibold">Code</th>
                            <th class="px-3 py-3 font-semibold">City</th>
                            <th class="px-3 py-3 font-semibold">Phone</th>
                            <th class="px-3 py-3 font-semibold">Status</th>
                            <th class="px-3 py-3 text-right font-semibold"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($branches as $branch)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                <td class="px-3 py-3 font-medium text-gray-900 dark:text-gray-50">{{ $branch->name }}</td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $branch->code }}</td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $branch->city ?? '—' }}</td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $branch->phone ?? '—' }}</td>
                                <td class="px-3 py-3">
                                    <x-ui.status-badge :status="$branch->is_active ? 'active' : 'inactive'" :label="$branch->is_active ? 'Active' : 'Inactive'" />
                                </td>
                                <td class="px-3 py-3 text-right">
                                    <a href="{{ route('branches.show', $branch) }}"
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

            <x-ui.pagination :paginator="$branches" />
        @endif
    </x-ui.card>
@endsection