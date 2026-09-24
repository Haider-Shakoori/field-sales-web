<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\CustomerPortalAccess;
use App\Models\Order;
use App\Services\CustomerStatementService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerPortalController extends Controller
{
    public function __invoke(
        Request $request,
        string $token,
        TenantContext $context,
        CustomerStatementService $statements,
    ): View {
        abort_unless(strlen($token) >= 32, 404);

        $access = $context->withPlatformScope(
            fn () => CustomerPortalAccess::query()
                ->where('token_hash', hash('sha256', $token))
                ->first(),
        );

        abort_unless($access && $access->isUsable(), 404);

        return $context->withTenant((int) $access->tenant_id, function () use (
            $access,
            $request,
            $statements,
        ): View {
            $access->loadMissing(['tenant', 'customer']);
            $customer = $access->customer;

            abort_unless($customer && $customer->is_active, 404);

            $validated = $request->validate([
                'from' => ['nullable', 'date_format:Y-m-d'],
                'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
                'currency' => ['nullable', Rule::in(['AFN', 'USD', 'PKR'])],
            ]);

            $timezone = $access->tenant?->timezone
                ?: config('app.timezone', 'UTC');
            $now = CarbonImmutable::now($timezone);
            $fromDate = $validated['from']
                ?? $now->subDays(89)->startOfDay()->toDateString();
            $toDate = $validated['to'] ?? $now->toDateString();
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

            $statement = $statements->build(
                $customer,
                $currency,
                $fromUtc,
                $toUtc,
            );

            $orders = Order::query()
                ->where('customer_id', $customer->id)
                ->where('status', 'approved')
                ->latest('ordered_at')
                ->limit(25)
                ->get();

            $collections = Collection::query()
                ->where('customer_id', $customer->id)
                ->where('status', 'verified')
                ->latest('collected_at')
                ->limit(25)
                ->get();

            $access->forceFill(['last_used_at' => now()])->save();

            return view('customer-portal.show', [
                'access' => $access,
                'customer' => $customer,
                'tenant' => $access->tenant,
                'statement' => $statement,
                'orders' => $orders,
                'collections' => $collections,
                'fromDate' => $fromDate,
                'toDate' => $toDate,
                'currency' => $currency,
                'timezone' => $timezone,
            ]);
        });
    }
}
