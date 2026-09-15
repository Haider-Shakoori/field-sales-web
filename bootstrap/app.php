<?php

use App\Http\Middleware\EnforceMinimumAppVersion;
use App\Http\Middleware\EnsureActiveDevice;
use App\Http\Middleware\InitializeTenancy;
use App\Support\Http\ApiResponse;
use App\Support\Tenancy\TenantContextMissingException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            InitializeTenancy::class,
        ]);

        $middleware->alias([
            'device.active' => EnsureActiveDevice::class,
            'app.version' => EnforceMinimumAppVersion::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->expectsJson()) {
                return ApiResponse::error(
                    'The given data was invalid.',
                    422,
                    ['errors' => $e->errors()],
                );
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson()) {
                return ApiResponse::error('Unauthenticated.', 401);
            }
        });

        $exceptions->render(function (TenantContextMissingException $e, Request $request) {
            if ($request->expectsJson()) {
                return ApiResponse::error(
                    'Tenant context is required before accessing tenant-owned data.',
                    500,
                );
            }
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            if ($request->expectsJson()) {
                return ApiResponse::error(
                    $e->getMessage() ?: (Response::$statusTexts[$e->getStatusCode()] ?? 'Error'),
                    $e->getStatusCode(),
                );
            }
        });
    })->create();
