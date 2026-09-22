<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerVisit;
use App\Models\VisitFormSubmission;
use App\Models\VisitFormTemplate;
use App\Services\VisitFormService;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VisitFormController extends Controller
{
    public function index(
        Request $request,
        VisitFormService $forms,
    ): JsonResponse {
        $validated = $request->validate([
            'customer_id' => ['nullable', 'uuid'],
            'visit_id' => ['nullable', 'uuid'],
        ]);

        $user = $request->user()->loadMissing(['tenant', 'salesman']);
        abort_unless($user->salesman?->is_active, 403);

        $visit = null;
        $customer = null;

        if (! empty($validated['visit_id'])) {
            $visit = CustomerVisit::with(['customer', 'formSubmissions'])
                ->where('uuid', $validated['visit_id'])
                ->where('user_id', $user->id)
                ->firstOrFail();
            $customer = $visit->customer;
        } elseif (! empty($validated['customer_id'])) {
            $customer = Customer::active()
                ->where('uuid', $validated['customer_id'])
                ->firstOrFail();
        }

        if (
            $visit
            && ! empty($validated['customer_id'])
            && $visit->customer?->uuid !== $validated['customer_id']
        ) {
            throw ValidationException::withMessages([
                'customer_id' => 'The selected customer does not match this visit.',
            ]);
        }

        $templates = $forms
            ->availableFor($user->salesman, $customer, $visit)
            ->map(
                fn (VisitFormTemplate $template): array => $this->templatePayload(
                    $template,
                    $visit,
                )
            )
            ->values()
            ->all();

        return ApiResponse::success([
            'templates' => $templates,
        ]);
    }

    public function store(
        Request $request,
        CustomerVisit $visit,
        VisitFormService $forms,
    ): JsonResponse {
        abort_unless((int) $visit->user_id === (int) $request->user()->id, 404);

        $validated = $request->validate([
            'offline_uuid' => ['required', 'uuid'],
            'template_id' => [
                'required',
                'uuid',
                Rule::exists('visit_form_templates', 'uuid')
                    ->where('tenant_id', $request->user()->tenant_id),
            ],
            'submitted_at' => ['nullable', 'date'],
            'answers' => ['required', 'array', 'max:50'],
            'answers.*.question_id' => ['required', 'uuid'],
            'answers.*.value' => ['nullable'],
        ]);

        $template = VisitFormTemplate::where('uuid', $validated['template_id'])
            ->firstOrFail();

        $existing = VisitFormSubmission::query()
            ->where(function ($query) use ($validated, $visit, $template): void {
                $query->where('uuid', $validated['offline_uuid'])
                    ->orWhere(function ($duplicate) use ($visit, $template): void {
                        $duplicate->where('visit_id', $visit->id)
                            ->where('template_id', $template->id);
                    });
            })
            ->exists();

        $submission = $forms->submit(
            $request->user(),
            $visit,
            $template,
            $validated['offline_uuid'],
            $validated['answers'],
            isset($validated['submitted_at'])
                ? CarbonImmutable::parse($validated['submitted_at'])->utc()
                : now()->toImmutable(),
        );

        return ApiResponse::success(
            $this->submissionPayload($submission),
            $existing ? 200 : 201,
        );
    }

    private function templatePayload(
        VisitFormTemplate $template,
        ?CustomerVisit $visit,
    ): array {
        $submission = $visit?->formSubmissions
            ?->firstWhere('template_id', $template->id);

        $scopeEntity = match ($template->scope_type) {
            'branch' => $template->branch,
            'territory' => $template->territory,
            'route' => $template->route,
            default => null,
        };

        return [
            'id' => $template->uuid,
            'code' => $template->code,
            'name' => $template->name,
            'description' => $template->description,
            'version' => $template->version,
            'required_on_checkout' => $template->required_on_checkout,
            'scope' => [
                'type' => $template->scope_type,
                'id' => $scopeEntity?->uuid,
                'name' => $scopeEntity?->name,
            ],
            'submission' => $submission ? [
                'id' => $submission->uuid,
                'submitted_at' => $submission->submitted_at?->toISOString(),
            ] : null,
            'questions' => $template->questions
                ->map(fn ($question): array => [
                    'id' => $question->uuid,
                    'label' => $question->label,
                    'help_text' => $question->help_text,
                    'type' => $question->type,
                    'options' => $question->options ?? [],
                    'validation' => $question->validation_rules ?? [],
                    'required' => $question->is_required,
                    'sort_order' => $question->sort_order,
                ])
                ->values()
                ->all(),
        ];
    }

    private function submissionPayload(VisitFormSubmission $submission): array
    {
        return [
            'id' => $submission->uuid,
            'visit_id' => $submission->visit?->uuid,
            'template_id' => $submission->template?->uuid,
            'template_name' => $submission->template_name,
            'template_version' => $submission->template_version,
            'submitted_at' => $submission->submitted_at?->toISOString(),
            'answers' => $submission->answers
                ->map(fn ($answer): array => [
                    'question_id' => $answer->question_uuid,
                    'label' => $answer->question_label,
                    'type' => $answer->question_type,
                    'value' => $answer->value,
                ])
                ->values()
                ->all(),
        ];
    }
}
