<?php

use App\Http\Middleware\DeviceRequired;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', api: __DIR__.'/../routes/api.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([\n            'device.required' => DeviceRequired::class,\n            'permission' => \\App\\Http\\Middleware\\PermissionRequired::class,\n        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*'));
        $exceptions->render(function (ValidationException $e, Request $request) {
            if (!$request->is('api/*')) return null;
            return ApiResponse::error('Validation failed.', 422, $e->errors(), 'VALIDATION_ERROR');
        });
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (!$request->is('api/*')) return null;
            return ApiResponse::error('Unauthenticated.', 401, null, 'UNAUTHENTICATED');
        });
        $exceptions->render(function (Throwable $e, Request $request) {
            if (!$request->is('api/*')) return null;
            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
            $message = $status >= 500 && !config('app.debug') ? 'Server error.' : $e->getMessage();
            return ApiResponse::error($message ?: 'Request failed.', $status, null, $status >= 500 ? 'SERVER_ERROR' : 'REQUEST_FAILED');
        });
    })->create();
