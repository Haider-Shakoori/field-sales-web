<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('audit:view'), 403);

        return view('admin.audit.index', [
            'logs' => AuditLog::with('actor')
                ->latest()
                ->paginate(50),
        ]);
    }
}
