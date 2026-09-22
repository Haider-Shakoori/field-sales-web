<x-layouts.app>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">{{ __('Visit forms') }}</h1>
            <p class="mt-1 text-sm text-slate-400">{{ __('Build reusable checklists and audits for field visits.') }}</p>
        </div>
        @if(auth()->user()->hasPermission('visits:manage'))
            <a href="{{ route('admin.visit-forms.create') }}" class="rounded-xl bg-indigo-500 px-4 py-2.5 font-semibold">
                {{ __('New visit form') }}
            </a>
        @endif
    </div>

    <div class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-white/5">
                <tr>
                    <th class="px-5 py-3">{{ __('Code') }}</th>
                    <th class="px-5 py-3">{{ __('Name') }}</th>
                    <th class="px-5 py-3">{{ __('Scope') }}</th>
                    <th class="px-5 py-3">{{ __('Questions') }}</th>
                    <th class="px-5 py-3">{{ __('Required at checkout') }}</th>
                    <th class="px-5 py-3">{{ __('Submissions') }}</th>
                    <th class="px-5 py-3">{{ __('Status') }}</th>
                    <th class="px-5 py-3"></th>
                </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                @forelse($templates as $template)
                    @php
                        $scopeName = match($template->scope_type) {
                            'branch' => $template->branch?->name,
                            'territory' => $template->territory?->name,
                            'route' => $template->route?->name,
                            default => __('All visits'),
                        };
                    @endphp
                    <tr>
                        <td class="px-5 py-4 font-mono text-xs">{{ $template->code }}</td>
                        <td class="px-5 py-4">
                            <div class="font-medium">{{ $template->name }}</div>
                            <div class="mt-1 text-xs text-slate-500">v{{ $template->version }}</div>
                        </td>
                        <td class="px-5 py-4">
                            <div>{{ __(str($template->scope_type)->title()->toString()) }}</div>
                            @if($template->scope_type !== 'all')
                                <div class="mt-1 text-xs text-slate-400">{{ $scopeName ?: '—' }}</div>
                            @endif
                        </td>
                        <td class="px-5 py-4">{{ $template->questions_count }}</td>
                        <td class="px-5 py-4">{{ $template->required_on_checkout ? __('Yes') : __('No') }}</td>
                        <td class="px-5 py-4">{{ $template->submissions_count }}</td>
                        <td class="px-5 py-4">
                            <span class="{{ $template->is_active ? 'text-emerald-300' : 'text-slate-500' }}">
                                {{ $template->is_active ? __('Active') : __('Inactive') }}
                            </span>
                        </td>
                        <td class="px-5 py-4 text-right">
                            @if(auth()->user()->hasPermission('visits:manage'))
                                <a href="{{ route('admin.visit-forms.edit', $template) }}" class="rounded-lg bg-white/10 px-3 py-2">
                                    {{ __('Edit') }}
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-5 py-10 text-center text-slate-400">
                            {{ __('No visit forms configured yet.') }}
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($templates->hasPages())
            <div class="border-t border-white/10 px-5 py-4">{{ $templates->links() }}</div>
        @endif
    </div>
</x-layouts.app>
