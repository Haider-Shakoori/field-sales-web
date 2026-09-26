<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileDiagnostic;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileDiagnosticController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'severity' => ['nullable', 'in:info,warning,error,critical'],
            'area' => ['required', 'string', 'max:80'],
            'code' => ['nullable', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:5000'],
            'context' => ['nullable', 'array'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        $device = $request->attributes->get('device');
        $context = collect($validated['context'] ?? [])
            ->only([
                'app_version',
                'screen',
                'operation',
                'entity',
                'sync_status',
                'network',
                'platform',
                'os_version',
            ])
            ->all();

        $diagnostic = MobileDiagnostic::create([
            'tenant_id' => $request->user()->tenant_id,
            'user_id' => $request->user()->id,
            'device_id' => $device?->id,
            'severity' => $validated['severity'] ?? 'error',
            'area' => $validated['area'],
            'code' => $validated['code'] ?? null,
            'message' => $validated['message'],
            'context' => $context === [] ? null : $context,
            'occurred_at' => $validated['occurred_at'] ?? now(),
        ]);

        return ApiResponse::success([
            'id' => $diagnostic->uuid,
            'stored' => true,
        ], 201);
    }
}
