<?php

use App\Http\Middleware\BootstrapTenantForApiAuth;
use App\Http\Middleware\BootstrapTenantForWebAuth;
use App\Http\Middleware\DeviceRequired;
use App\Http\Middleware\PermissionRequired;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Web identity resolution requires StartSession to have already run.
        // Appending keeps the guest/public path fail-closed while ensuring the
        // authenticated user can be resolved before route auth/controllers.
        $middleware->web(append: [
            BootstrapTenantForWebAuth::class,
        ]);

        $middleware->api(prepend: [
            BootstrapTenantForApiAuth::class,
        ]);

        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: BootstrapTenantForWebAuth::class,
        );

        $middleware->alias([
            'device.required' => DeviceRequired::class,
            'permission' => PermissionRequired::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
        );

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                'Validation failed.',
                422,
                $exception->errors(),
                'VALIDATION_ERROR'
            );
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                'Unauthenticated.',
                401,
                null,
                'UNAUTHENTICATED'
            );
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $exception instanceof HttpExceptionInterface
                ? $exception->getStatusCode()
                : 500;

            $message = $status >= 500 && ! config('app.debug')
                ? 'Server error.'
                : ($exception->getMessage() ?: 'Request failed.');

            return ApiResponse::error(
                $message,
                $status,
                null,
                $status >= 500 ? 'SERVER_ERROR' : 'REQUEST_FAILED'
            );
        });
    })
    ->create();
