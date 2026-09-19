<?php

namespace App\Http\Controllers;

use App\Support\ProductionReadiness;
use Illuminate\Http\JsonResponse;

class ReadinessController extends Controller
{
    public function __invoke(ProductionReadiness $readiness): JsonResponse
    {
        $ready = $readiness->servicesAreReady();

        return response()
            ->json(
                ['status' => $ready ? 'ready' : 'unavailable'],
                $ready ? 200 : 503,
            )
            ->withHeaders([
                'Cache-Control' => 'no-store, private',
                'X-Robots-Tag' => 'noindex, nofollow',
            ]);
    }
}
