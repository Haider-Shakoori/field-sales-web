<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\CustomerStatementService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerStatementController extends Controller
{
    public function __invoke(
        Request $request,
        Customer $customer,
        CustomerStatementService $statements,
    ): View {
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

        $fromUtc = CarbonImmutable::parse($fromDate, $timezone)
            ->startOfDay()
            ->utc();
        $toUtc = CarbonImmutable::parse($toDate, $timezone)
            ->endOfDay()
            ->utc();

        return view('admin.customers.statement', [
            'customer' => $customer,
            'tenant' => $tenant,
            'statement' => $statements->build(
                $customer,
                $currency,
                $fromUtc,
                $toUtc,
            ),
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'timezone' => $timezone,
        ]);
    }
}
