<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerVisit;
use App\Models\RouteCustomer;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use App\Models\User;
use App\Models\VisitFormQuestion;
use App\Models\VisitFormSubmission;
use App\Models\VisitFormTemplate;
use App\Models\VisitPhoto;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VisitFormService
{
    public function availableFor(
        Salesman $salesman,
        ?Customer $customer = null,
        ?CustomerVisit $visit = null,
    ): Collection {
        $salesman->loadMissing('user.tenant');

        $timezone = $salesman->user?->tenant?->timezone
            ?: config('app.timezone', 'UTC');
        $localDate = now($timezone)->toDateString();

        $assignment = SalesmanAssignment::query()
            ->where('salesman_id', $salesman->id)
            ->current($localDate)
            ->latest('effective_from')
            ->first();

        $branchId = $customer?->branch_id ?? $assignment?->branch_id;
        $territoryId = $customer?->territory_id ?? $assignment?->territory_id;
        $routeId = $visit?->route_id;

        if (! $routeId && $assignment?->route_id) {
            $routeMatchesCustomer = ! $customer
                || RouteCustomer::query()
                    ->where('route_id', $assignment->route_id)
                    ->where('customer_id', $customer->id)
                    ->exists();

            if ($routeMatchesCustomer) {
                $routeId = $assignment->route_id;
            }
        }

        return VisitFormTemplate::with('questions')
            ->where('is_active', true)
            ->where(function ($query) use ($branchId, $territoryId, $routeId): void {
                $query->where('scope_type', 'all');

                if ($branchId) {
                    $query->orWhere(function ($scope) use ($branchId): void {
                        $scope->where('scope_type', 'branch')
                            ->where('branch_id', $branchId);
                    });
                }

                if ($territoryId) {
                    $query->orWhere(function ($scope) use ($territoryId): void {
                        $scope->where('scope_type', 'territory')
                            ->where('territory_id', $territoryId);
                    });
                }

                if ($routeId) {
                    $query->orWhere(function ($scope) use ($routeId): void {
                        $scope->where('scope_type', 'route')
                            ->where('route_id', $routeId);
                    });
                }
            })
            ->orderBy('name')
            ->get();
    }

    public function missingRequiredForVisit(CustomerVisit $visit): Collection
    {
        $visit->loadMissing(['salesman.user.tenant', 'customer', 'formSubmissions']);

        return $this->availableFor(
            $visit->salesman,
            $visit->customer,
            $visit,
        )
            ->where('required_on_checkout', true)
            ->reject(
                fn (VisitFormTemplate $template): bool => $visit->formSubmissions
                    ->contains('template_id', $template->id)
            )
            ->values();
    }

    public function submit(
        User $user,
        CustomerVisit $visit,
        VisitFormTemplate $template,
        string $offlineUuid,
        int $templateVersion,
        array $answers,
        CarbonImmutable $submittedAt,
    ): VisitFormSubmission {
        $visit->loadMissing(['salesman.user.tenant', 'customer']);

        if ((int) $visit->user_id !== (int) $user->id) {
            abort(404);
        }

        $existing = VisitFormSubmission::where('uuid', $offlineUuid)->first();

        if ($existing) {
            if (
                (int) $existing->visit_id !== (int) $visit->id
                || (int) $existing->template_id !== (int) $template->id
                || (int) $existing->user_id !== (int) $user->id
            ) {
                abort(409);
            }

            return $existing->load(['template', 'answers']);
        }

        $duplicate = VisitFormSubmission::query()
            ->where('visit_id', $visit->id)
            ->where('template_id', $template->id)
            ->first();

        if ($duplicate) {
            return $duplicate->load(['template', 'answers']);
        }

        $applicable = $this->availableFor(
            $visit->salesman,
            $visit->customer,
            $visit,
        )->contains('id', $template->id);

        if (! $applicable || ! $template->is_active) {
            throw ValidationException::withMessages([
                'template_id' => 'This visit form does not apply to the selected customer visit.',
            ]);
        }

        if ($templateVersion < 1 || $templateVersion > $template->version) {
            throw ValidationException::withMessages([
                'template_version' => 'This visit form version is not available.',
            ]);
        }

        $questions = VisitFormQuestion::query()
            ->where('template_id', $template->id)
            ->where('template_version', $templateVersion)
            ->orderBy('sort_order')
            ->get();

        if ($questions->isEmpty()) {
            throw ValidationException::withMessages([
                'template_version' => 'This visit form version is not available.',
            ]);
        }

        $normalizedAnswers = $this->validateAnswers(
            $visit,
            $questions,
            $answers,
        );

        if ($submittedAt->gt(now()->addMinutes(5))) {
            throw ValidationException::withMessages([
                'submitted_at' => 'Submission time is too far in the future.',
            ]);
        }

        return DB::transaction(function () use (
            $user,
            $visit,
            $template,
            $offlineUuid,
            $templateVersion,
            $submittedAt,
            $normalizedAnswers,
        ): VisitFormSubmission {
            $submission = VisitFormSubmission::create([
                'uuid' => $offlineUuid,
                'visit_id' => $visit->id,
                'template_id' => $template->id,
                'customer_id' => $visit->customer_id,
                'user_id' => $user->id,
                'salesman_id' => $visit->salesman_id,
                'template_version' => $templateVersion,
                'template_name' => $template->name,
                'submitted_at' => $submittedAt,
            ]);

            foreach ($normalizedAnswers as $answer) {
                $submission->answers()->create($answer);
            }

            return $submission->fresh()->load(['template', 'answers']);
        });
    }

    private function validateAnswers(
        CustomerVisit $visit,
        Collection $questions,
        array $answers,
    ): array {
        $provided = collect($answers);

        if ($provided->pluck('question_id')->filter()->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'answers' => 'Each visit form question may only be answered once.',
            ]);
        }

        $answerMap = $provided
            ->filter(fn ($answer) => is_array($answer) && isset($answer['question_id']))
            ->keyBy('question_id');

        $knownQuestionIds = $questions->pluck('uuid');

        $unknown = $answerMap->keys()->diff($knownQuestionIds);

        if ($unknown->isNotEmpty()) {
            throw ValidationException::withMessages([
                'answers' => 'One or more answers reference a question outside this template.',
            ]);
        }

        $normalized = [];

        foreach ($questions as $question) {
            $raw = $answerMap->get($question->uuid);
            $value = is_array($raw) ? ($raw['value'] ?? null) : null;

            if ($question->is_required && $this->blank($value)) {
                throw ValidationException::withMessages([
                    'answers.'.$question->uuid => $question->label.' is required.',
                ]);
            }

            if ($this->blank($value)) {
                continue;
            }

            $normalized[] = [
                'question_id' => $question->id,
                'question_uuid' => $question->uuid,
                'question_label' => $question->label,
                'question_type' => $question->type,
                'value' => $this->normalizeValue($visit, $question, $value),
            ];
        }

        return $normalized;
    }

    private function normalizeValue(
        CustomerVisit $visit,
        VisitFormQuestion $question,
        mixed $value,
    ): array {
        return match ($question->type) {
            'text', 'textarea' => [
                'value' => $this->stringValue($question, $value),
            ],
            'number' => [
                'value' => $this->numberValue($question, $value),
            ],
            'yes_no' => [
                'value' => $this->booleanValue($question, $value),
            ],
            'single_choice' => [
                'value' => $this->singleChoiceValue($question, $value),
            ],
            'multi_choice' => [
                'value' => $this->multiChoiceValue($question, $value),
            ],
            'date' => [
                'value' => $this->dateValue($question, $value),
            ],
            'photo' => [
                'photo_id' => $this->photoValue($visit, $question, $value),
            ],
            default => throw ValidationException::withMessages([
                'answers.'.$question->uuid => 'Unsupported visit form question type.',
            ]),
        };
    }

    private function stringValue(
        VisitFormQuestion $question,
        mixed $value,
    ): string {
        if (! is_string($value)) {
            $this->invalid($question, 'must be text');
        }

        $value = trim($value);

        if (mb_strlen($value) > 5000) {
            $this->invalid($question, 'must not exceed 5000 characters');
        }

        return $value;
    }

    private function numberValue(
        VisitFormQuestion $question,
        mixed $value,
    ): float|int {
        if (! is_numeric($value)) {
            $this->invalid($question, 'must be a number');
        }

        $number = $value + 0;
        $rules = $question->validation_rules ?? [];

        if (isset($rules['min']) && $number < (float) $rules['min']) {
            $this->invalid($question, 'is below the allowed minimum');
        }

        if (isset($rules['max']) && $number > (float) $rules['max']) {
            $this->invalid($question, 'is above the allowed maximum');
        }

        return $number;
    }

    private function booleanValue(
        VisitFormQuestion $question,
        mixed $value,
    ): bool {
        $normalized = filter_var(
            $value,
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE,
        );

        if ($normalized === null) {
            $this->invalid($question, 'must be yes or no');
        }

        return $normalized;
    }

    private function singleChoiceValue(
        VisitFormQuestion $question,
        mixed $value,
    ): string {
        if (! is_scalar($value)) {
            $this->invalid($question, 'must be one of the configured options');
        }

        $value = (string) $value;

        if (! in_array($value, $question->options ?? [], true)) {
            $this->invalid($question, 'must be one of the configured options');
        }

        return $value;
    }

    private function multiChoiceValue(
        VisitFormQuestion $question,
        mixed $value,
    ): array {
        if (! is_array($value) || $value === []) {
            $this->invalid($question, 'must contain at least one configured option');
        }

        $values = array_values(array_unique(array_map('strval', $value)));
        $invalid = array_diff($values, $question->options ?? []);

        if ($invalid !== []) {
            $this->invalid($question, 'contains an option that is not configured');
        }

        return $values;
    }

    private function dateValue(
        VisitFormQuestion $question,
        mixed $value,
    ): string {
        if (! is_string($value)) {
            $this->invalid($question, 'must be a date in YYYY-MM-DD format');
        }

        $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        if (! $parsed || $parsed->format('Y-m-d') !== $value) {
            $this->invalid($question, 'must be a date in YYYY-MM-DD format');
        }

        return $value;
    }

    private function photoValue(
        CustomerVisit $visit,
        VisitFormQuestion $question,
        mixed $value,
    ): string {
        if (! is_string($value)) {
            $this->invalid($question, 'must reference a visit photo');
        }

        $photo = VisitPhoto::query()
            ->where('uuid', $value)
            ->where('visit_id', $visit->id)
            ->first();

        if (! $photo) {
            $this->invalid($question, 'must reference a photo uploaded for this visit');
        }

        return $photo->uuid;
    }

    private function blank(mixed $value): bool
    {
        return $value === null
            || (is_string($value) && trim($value) === '')
            || (is_array($value) && $value === []);
    }

    private function invalid(
        VisitFormQuestion $question,
        string $message,
    ): never {
        throw ValidationException::withMessages([
            'answers.'.$question->uuid => $question->label.' '.$message.'.',
        ]);
    }
}
