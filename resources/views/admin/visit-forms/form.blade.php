<x-layouts.app>
    @php
        $editing = $template->exists;
        $existingQuestions = old('questions');

        if ($existingQuestions === null) {
            $existingQuestions = $editing
                ? $template->questions->map(fn ($question) => [
                    'label' => $question->label,
                    'help_text' => $question->help_text,
                    'type' => $question->type,
                    'options_text' => implode("\n", $question->options ?? []),
                    'is_required' => $question->is_required ? '1' : '0',
                    'min' => $question->validation_rules['min'] ?? '',
                    'max' => $question->validation_rules['max'] ?? '',
                ])->all()
                : [[
                    'label' => '',
                    'help_text' => '',
                    'type' => 'yes_no',
                    'options_text' => '',
                    'is_required' => '0',
                    'min' => '',
                    'max' => '',
                ]];
        }
    @endphp

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">{{ $editing ? __('Edit visit form') : __('New visit form') }}</h1>
            <p class="mt-1 text-sm text-slate-400">{{ __('Configure questions once and reuse them across customer visits.') }}</p>
        </div>
        <a href="{{ route('admin.visit-forms.index') }}" class="rounded-xl bg-white/10 px-4 py-2.5">{{ __('Back') }}</a>
    </div>

    <form method="POST" action="{{ $editing ? route('admin.visit-forms.update', $template) : route('admin.visit-forms.store') }}" class="space-y-5">
        @csrf
        @if($editing) @method('PUT') @endif

        <section class="grid gap-4 rounded-2xl border border-white/10 bg-slate-900 p-5 lg:grid-cols-2">
            <div>
                <label class="mb-2 block text-sm text-slate-300">{{ __('Code') }}</label>
                <input name="code" value="{{ old('code', $template->code) }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
            </div>
            <div>
                <label class="mb-2 block text-sm text-slate-300">{{ __('Name') }}</label>
                <input name="name" value="{{ old('name', $template->name) }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
            </div>
            <div class="lg:col-span-2">
                <label class="mb-2 block text-sm text-slate-300">{{ __('Description') }}</label>
                <textarea name="description" rows="3" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">{{ old('description', $template->description) }}</textarea>
            </div>
            <div>
                <label class="mb-2 block text-sm text-slate-300">{{ __('Scope') }}</label>
                <select name="scope_type" id="scope-type" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
                    @foreach(AppModelsVisitFormTemplate::SCOPE_TYPES as $scope)
                        <option value="{{ $scope }}" @selected(old('scope_type', $template->scope_type ?: 'all') === $scope)>
                            {{ __(str($scope)->title()->toString()) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-3">
                <select name="branch_id" data-scope="branch" class="scope-target rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
                    <option value="">{{ __('Select branch') }}</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((string) old('branch_id', $template->branch_id) === (string) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
                <select name="territory_id" data-scope="territory" class="scope-target rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
                    <option value="">{{ __('Select territory') }}</option>
                    @foreach($territories as $territory)
                        <option value="{{ $territory->id }}" @selected((string) old('territory_id', $template->territory_id) === (string) $territory->id)>{{ $territory->name }}</option>
                    @endforeach
                </select>
                <select name="route_id" data-scope="route" class="scope-target rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
                    <option value="">{{ __('Select route') }}</option>
                    @foreach($routes as $route)
                        <option value="{{ $route->id }}" @selected((string) old('route_id', $template->route_id) === (string) $route->id)>{{ $route->name }}</option>
                    @endforeach
                </select>
            </div>
            <label class="flex items-center gap-3 rounded-xl bg-white/5 px-4 py-3">
                <input type="hidden" name="required_on_checkout" value="0">
                <input type="checkbox" name="required_on_checkout" value="1" @checked(old('required_on_checkout', $template->required_on_checkout))>
                <span>
                    <span class="block font-medium">{{ __('Required before checkout') }}</span>
                    <span class="text-xs text-slate-400">{{ __('Salesmen cannot complete an applicable visit until this form is submitted.') }}</span>
                </span>
            </label>
            <label class="flex items-center gap-3 rounded-xl bg-white/5 px-4 py-3">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $template->exists ? $template->is_active : true))>
                <span>
                    <span class="block font-medium">{{ __('Active') }}</span>
                    <span class="text-xs text-slate-400">{{ __('Inactive forms are not delivered to field devices.') }}</span>
                </span>
            </label>
        </section>

        <section class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-semibold">{{ __('Questions') }}</h2>
                    <p class="mt-1 text-xs text-slate-400">{{ __('Drag-free order: questions are saved in the order shown below.') }}</p>
                </div>
                <button type="button" id="add-question" class="rounded-xl bg-white/10 px-4 py-2 text-sm font-semibold">{{ __('Add question') }}</button>
            </div>

            <div id="question-list" class="space-y-4">
                @foreach($existingQuestions as $index => $question)
                    @include('admin.visit-forms.question-row', ['index' => $index, 'question' => $question])
                @endforeach
            </div>
        </section>

        <div class="flex justify-end">
            <button class="rounded-xl bg-indigo-500 px-5 py-3 font-semibold">{{ __('Save visit form') }}</button>
        </div>
    </form>

    <template id="question-row-template">
        @include('admin.visit-forms.question-row', [
            'index' => '__INDEX__',
            'question' => [
                'label' => '',
                'help_text' => '',
                'type' => 'yes_no',
                'options_text' => '',
                'is_required' => '0',
                'min' => '',
                'max' => '',
            ],
        ])
    </template>

    <script>
        (() => {
            const list = document.getElementById('question-list');
            const template = document.getElementById('question-row-template');
            const addButton = document.getElementById('add-question');
            const scopeType = document.getElementById('scope-type');

            const refreshScope = () => {
                document.querySelectorAll('.scope-target').forEach((element) => {
                    element.hidden = element.dataset.scope !== scopeType.value;
                });
            };

            const refreshNumbers = () => {
                [...list.querySelectorAll('[data-question-row]')].forEach((row, index) => {
                    row.querySelector('[data-number]').textContent = index + 1;
                });
            };

            const addQuestion = () => {
                const index = Date.now().toString();
                const wrapper = document.createElement('div');
                wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', index).trim();
                list.appendChild(wrapper.firstElementChild);
                refreshNumbers();
            };

            addButton.addEventListener('click', addQuestion);
            list.addEventListener('click', (event) => {
                const button = event.target.closest('[data-remove-question]');

                if (!button) return;
                if (list.querySelectorAll('[data-question-row]').length <= 1) return;

                button.closest('[data-question-row]').remove();
                refreshNumbers();
            });
            scopeType.addEventListener('change', refreshScope);

            refreshScope();
            refreshNumbers();
        })();
    </script>
</x-layouts.app>
