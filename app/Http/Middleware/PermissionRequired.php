<?php
namespace App\Http\Middleware;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class PermissionRequired
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        if (! $user || ! $user->hasPermission($permission)) {
            if ($request->is('api/*')) {
                return ApiResponse::error('Forbidden.', 403, null, 'FORBIDDEN');
            }
            abort(403);
        }
        return $next($request);
    }
}
