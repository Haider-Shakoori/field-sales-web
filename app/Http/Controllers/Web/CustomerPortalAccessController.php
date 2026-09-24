<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerPortalAccess;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CustomerPortalAccessController extends Controller
{
    public function store(
        Request $request,
        Customer $customer,
        AuditLogger $audit,
    ): RedirectResponse {
        Gate::authorize('update', $customer);

        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:120'],
            'expires_days' => ['required', 'integer', 'between:1,365'],
        ]);

        $token = Str::random(64);
        $access = CustomerPortalAccess::create([
            'customer_id' => $customer->id,
            'created_by' => $request->user()->id,
            'label' => $validated['label'] ?? null,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays((int) $validated['expires_days']),
        ]);

        $audit->record('customer.portal_access_created', $access, [], [
            'customer_id' => $customer->id,
            'label' => $access->label,
            'expires_at' => $access->expires_at?->toISOString(),
        ]);

        return back()
            ->with('status', 'Customer portal link created. Copy it now; the secret is not stored.')
            ->with('portal_url', route('customer-portal.show', ['token' => $token]));
    }

    public function destroy(
        Request $request,
        Customer $customer,
        CustomerPortalAccess $access,
        AuditLogger $audit,
    ): RedirectResponse {
        Gate::authorize('update', $customer);
        abort_unless((int) $access->customer_id === (int) $customer->id, 404);

        if ($access->revoked_at === null) {
            $access->update(['revoked_at' => now()]);

            $audit->record('customer.portal_access_revoked', $access, [], [
                'customer_id' => $customer->id,
                'revoked_at' => $access->revoked_at?->toISOString(),
            ]);
        }

        return back()->with('status', 'Customer portal link revoked.');
    }
}
