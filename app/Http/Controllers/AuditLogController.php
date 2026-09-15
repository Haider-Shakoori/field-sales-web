<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', AuditLog::class);

        $query = AuditLog::query()->with('user');

        if (TenantContext::hasContext()) {
            $query->where('tenant_id', TenantContext::currentId());
        } else {
            $query->whereNull('tenant_id');
        }

        if ($event = $request->string('event')->toString()) {
            $query->where('event', 'like', "%{$event}%");
        }

        if ($user = $request->string('user')->toString()) {
            $query->where('user_id', $user);
        }

        if ($from = $request->string('from')->toString()) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->string('to')->toString()) {
            $query->whereDate('created_at', '<=', $to);
        }

        $logs = $query->orderByDesc('created_at')->paginate(25)->withQueryString();

        return view('pages.audit.index', compact('logs'));
    }
}
