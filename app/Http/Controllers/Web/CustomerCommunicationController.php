<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\AuditLogger;
use App\Services\CustomerCommunicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CustomerCommunicationController extends Controller
{
    public function store(
        Request $request,
        Customer $customer,
        CustomerCommunicationService $communications,
        AuditLogger $audit,
    ): RedirectResponse {
        Gate::authorize('update', $customer);

        $validated = $request->validate([
            'channel' => ['required', Rule::in(CustomerCommunicationService::CHANNELS)],
            'kind' => ['required', Rule::in([
                'custom',
                'payment_reminder',
                'order_update',
                'statement',
            ])],
            'message' => ['required', 'string', 'max:1600'],
        ]);

        $delivery = $communications->queue(
            $customer,
            $request->user(),
            $validated['channel'],
            $validated['message'],
            $validated['kind'],
        );

        $audit->record('customer.communication_queued', $delivery, [], [
            'customer_id' => $customer->id,
            'channel' => $delivery->channel,
            'kind' => $delivery->kind,
            'status' => $delivery->status,
            'recipient_phone' => $delivery->recipient_phone,
        ]);

        $status = $delivery->status === 'skipped'
            ? ucfirst($delivery->channel).' message recorded but provider delivery is disabled.'
            : ucfirst($delivery->channel).' message queued.';

        return back()->with('status', $status);
    }
}
