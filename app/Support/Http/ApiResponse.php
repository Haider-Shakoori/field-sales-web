<?php

namespace App\Support\Http;

use Illuminate\Http\JsonResponse;

/**
 * Standard JSON envelope for every API response:
 *
 * { "success": bool, "data": mixed, "meta": object, "error": object }
 *
 * Error responses carry a stable machine-readable `error.code` (null when no
 * specific domain code applies) plus the human `error.message` and optional
 * `error.details`.
 */
class ApiResponse
{
    public static function success(mixed $data = null, ?array $meta = null, ?string $message = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => $meta ?? (object) [],
            'error' => null,
        ], $status, [], JSON_UNESCAPED_UNICODE);
    }

    public static function error(string $message, int $status = 400, mixed $details = null, ?string $code = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'data' => null,
            'meta' => (object) [],
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
        ], $status, [], JSON_UNESCAPED_UNICODE);
    }
}
