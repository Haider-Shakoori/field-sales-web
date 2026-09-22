<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\SalesRoute;
use App\Models\Territory;
use App\Models\VisitFormQuestion;
use App\Models\VisitFormTemplate;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class VisitFormTemplateController extends Controller
{
    public function index(): View
    {
        return view('admin.visit-forms.index', [
            'templates' => VisitFormTemplate::with([
                'branch',
                'territory',
                'route',
            ])
                ->withCount(['questions', 'submissions'])
                ->orderBy('name')
                ->paginate(30),
        ]);
    }

    public function create(): View
    {
        return view('admin.visit-forms.form', [
            'template' => new VisitFormTemplate([
                'scope_type' => 'all',
                'is_active' => true,
            ]),
            ...$this->formData(),
        ]);
    }

    public function store(
        Request $request,
        AuditLogger $audit,
    ): RedirectResponse {
        $validated = $this->validatePayload($request);
        $scope = $this->scopeAttributes($validated);
        $questions = $this->normalizeQuestions($validated['questions']);

        $template = DB::transaction(function () use (
            $request,
            $validated,
            $scope,
            $questions,
        ): VisitFormTemplate {
            $template = VisitFormTemplate::create([
                'code' => strtoupper($validated['code']),
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                ...$scope,
                'version' => 1,
                'required_on_checkout' => $request->boolean('required_on_checkout'),
                'is_active' => $request->boolean('is_active'),
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            $this->replaceQuestions($template, $questions);

            return $template->fresh()->load('questions');
        });

        $audit->record('visit_form_template.created', $template, [], [
            'code' => $template->code,
            'name' => $template->name,
            'scope_type' => $template->scope_type,
            'required_on_checkout' => $template->required_on_checkout,
            'question_count' => $template->questions->count(),
        ]);

        return redirect()
            ->route('admin.visit-forms.edit', $template)
            ->with('status', __('Visit form created.'));
    }

    public function edit(VisitFormTemplate $visitForm): View
    {
        return view('admin.visit-forms.form', [
            'template' => $visitForm->load('questions'),
            ...$this->formData(),
        ]);
    }

    public function update(
        Request $request,
        VisitFormTemplate $visitForm,
        AuditLogger $audit,
    ): RedirectResponse {
        $validated = $this->validatePayload($request, $visitForm);
        $scope = $this->scopeAttributes($validated);
        $questions = $this->normalizeQuestions($validated['questions']);

        $before = [
            'code' => $visitForm->code,
            'name' => $visitForm->name,
            'scope_type' => $visitForm->scope_type,
            'required_on_checkout' => $visitForm->required_on_checkout,
            'is_active' => $visitForm->is_active,
            'version' => $visitForm->version,
        ];

        DB::transaction(function () use (
            $request,
            $validated,
            $scope,
            $questions,
            $visitForm,
        ): void {
            $visitForm->update([
                'code' => strtoupper($validated['code']),
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                ...$scope,
                'version' => $visitForm->version + 1,
                'required_on_checkout' => $request->boolean('required_on_checkout'),
                'is_active' => $request->boolean('is_active'),
                'updated_by' => $request->user()->id,
            ]);

            $visitForm->questions()->delete();
            $this->replaceQuestions($visitForm, $questions);
        });

        $visitForm->refresh()->load('questions');

        $audit->record('visit_form_template.updated', $visitForm, $before, [
            'code' => $visitForm->code,
            'name' => $visitForm->name,
            'scope_type' => $visitForm->scope_type,
            'required_on_checkout' => $visitForm->required_on_checkout,
            'is_active' => $visitForm->is_active,
            'version' => $visitForm->version,
            'question_count' => $visitForm->questions->count(),
        ]);

        return back()->with('status', __('Visit form updated.'));
    }

    private function validatePayload(
        Request $request,
        ?VisitFormTemplate $template = null,
    ): array {
        $tenantId = $request->user()->tenant_id;

        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:60',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('visit_form_templates', 'code')
                    ->where('tenant_id', $tenantId)
                    ->ignore($template?->id),
            ],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'scope_type' => ['required', Rule::in(VisitFormTemplate::SCOPE_TYPES)],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where('tenant_id', $tenantId),
            ],
            'territory_id' => [
                'nullable',
                'integer',
                Rule::exists('territories', 'id')->where('tenant_id', $tenantId),
            ],
            'route_id' => [
                'nullable',
                'integer',
                Rule::exists('routes', 'id')->where('tenant_id', $tenantId),
            ],
            'required_on_checkout' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'questions' => ['required', 'array', 'min:1', 'max:50'],
            'questions.*.label' => ['required', 'string', 'max:255'],
            'questions.*.help_text' => ['nullable', 'string', 'max:1000'],
            'questions.*.type' => ['required', Rule::in(VisitFormQuestion::TYPES)],
            'questions.*.options_text' => ['nullable', 'string', 'max:5000'],
            'questions.*.is_required' => ['nullable', 'boolean'],
            'questions.*.min' => ['nullable', 'numeric'],
            'questions.*.max' => ['nullable', 'numeric'],
        ]);
    }

    private function scopeAttributes(array $validated): array
    {
        $scope = $validated['scope_type'];

        if ($scope === 'branch' && empty($validated['branch_id'])) {
            throw ValidationException::withMessages([
                'branch_id' => 'Select a branch for a branch-scoped form.',
            ]);
        }

        if ($scope === 'territory' && empty($validated['territory_id'])) {
            throw ValidationException::withMessages([
                'territory_id' => 'Select a territory for a territory-scoped form.',
            ]);
        }

        if ($scope === 'route' && empty($validated['route_id'])) {
            throw ValidationException::withMessages([
                'route_id' => 'Select a route for a route-scoped form.',
            ]);
        }

        return [
            'scope_type' => $scope,
            'branch_id' => $scope === 'branch' ? $validated['branch_id'] : null,
            'territory_id' => $scope === 'territory' ? $validated['territory_id'] : null,
            'route_id' => $scope === 'route' ? $validated['route_id'] : null,
        ];
    }

    private function normalizeQuestions(array $questions): array
    {
        return collect($questions)
            ->values()
            ->map(function (array $question, int $index): array {
                $options = collect(
                    preg_split('/\r\n|\r|\n/', (string) ($question['options_text'] ?? ''))
                )
                    ->map(fn (string $option) => trim($option))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if (
                    in_array($question['type'], ['single_choice', 'multi_choice'], true)
                    && count($options) < 2
                ) {
                    throw ValidationException::withMessages([
                        'questions.'.$index.'.options_text' => 'Choice questions require at least two options.',
                    ]);
                }

                $rules = [];

                if ($question['type'] === 'number') {
                    if (isset($question['min']) && $question['min'] !== '') {
                        $rules['min'] = (float) $question['min'];
                    }

                    if (isset($question['max']) && $question['max'] !== '') {
                        $rules['max'] = (float) $question['max'];
                    }

                    if (
                        isset($rules['min'], $rules['max'])
                        && $rules['min'] > $rules['max']
                    ) {
                        throw ValidationException::withMessages([
                            'questions.'.$index.'.max' => 'Maximum must be greater than or equal to minimum.',
                        ]);
                    }
                }

                return [
                    'label' => trim($question['label']),
                    'help_text' => trim((string) ($question['help_text'] ?? '')) ?: null,
                    'type' => $question['type'],
                    'options' => $options ?: null,
                    'validation_rules' => $rules ?: null,
                    'is_required' => filter_var(
                        $question['is_required'] ?? false,
                        FILTER_VALIDATE_BOOLEAN,
                    ),
                    'sort_order' => $index + 1,
                ];
            })
            ->all();
    }

    private function replaceQuestions(
        VisitFormTemplate $template,
        array $questions,
    ): void {
        foreach ($questions as $question) {
            $template->questions()->create($question);
        }
    }

    private function formData(): array
    {
        return [
            'branches' => Branch::orderBy('name')->get(),
            'territories' => Territory::orderBy('name')->get(),
            'routes' => SalesRoute::orderBy('name')->get(),
            'questionTypes' => VisitFormQuestion::TYPES,
        ];
    }
}
