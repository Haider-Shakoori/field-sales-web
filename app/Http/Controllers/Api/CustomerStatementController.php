<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\CustomerStatementService;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerStatementController extends Controller
{
    public function __invoke(
        Request $request,
        Customer $customer,
        CustomerStatementService $statements,
    ): JsonResponse {
        abort_unless($request->user()->hasPermission('customers:view'), 403);

        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'currency' => ['nullable', Rule::in(['AFN', 'USD', 'PKR'])],
        ]);

        $tenant = $request->user()->loadMissing('tenant')->tenant;
        $timezone = $tenant?->timezone ?: config('app.timezone', 'UTC');
        $localNow = CarbonImmutable::now($timezone);

        $fromDate = $validated['from']
            ?? $localNow->startOfMonth()->toDateString();
        $toDate = $validated['to'] ?? $localNow->toDateString();
        $currency = strtoupper(
            $validated['currency']
                ?? $customer->credit_currency
                ?? 'AFN',
        );

        $statement = $statements->build(
            $customer,
            $currency,
            CarbonImmutable::parse($fromDate, $timezone)->startOfDay()->utc(),
            CarbonImmutable::parse($toDate, $timezone)->endOfDay()->utc(),
        );

        return ApiResponse::success([
            'customer' => [
                'id' => $customer->uuid,
                'code' => $customer->code,
                'name' => $customer->name,
            ],
            'from' => $fromDate,
            'to' => $toDate,
            'timezone' => $timezone,
            'currency' => $statement['currency'],
            'opening_balance' => $statement['opening_balance'],
            'debits' => $statement['debits'],
            'credits' => $statement['credits'],
            'closing_balance' => $statement['closing_balance'],
            'entries' => collect($statement['entries'])
                ->map(fn (array $entry): array => [
                    ...$entry,
                    'occurred_at' => $entry['occurred_at']->toISOString(),
                ])
                ->values()
                ->all(),
        ]);
    }
}
