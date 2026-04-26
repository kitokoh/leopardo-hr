<?php

use App\Exceptions\DomainException;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TenantMiddleware;
use App\Http\Middleware\Web\EnsureEmployeeMiddleware;
use App\Http\Middleware\Web\EnsureManagerMiddleware;
use App\Http\Middleware\Web\EnsureManagerRoleMiddleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [SetLocale::class]);

        $middleware->alias([
            'tenant' => TenantMiddleware::class,
            'manager' => EnsureManagerMiddleware::class,
            'manager_role' => EnsureManagerRoleMiddleware::class,
            'employee' => EnsureEmployeeMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (DomainException $exception, Request $request) {
            if (! ($request->expectsJson() || $request->is('api/*'))) {
                return null;
            }

            $errorCode = $exception->errorCode();
            $translatedMessage = __('errors.'.$errorCode);
            $message = $translatedMessage !== 'errors.'.$errorCode
                ? $translatedMessage
                : $exception->getMessage();

            return new JsonResponse([
                'error' => $errorCode,
                'message' => $message,
            ], $exception->statusCode());
        });

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! ($request->expectsJson() || $request->is('api/*'))) {
                return null;
            }

            return new JsonResponse([
                'error' => 'VALIDATION_ERROR',
                'message' => __('errors.VALIDATION_ERROR'),
                'errors' => $exception->errors(),
            ], 422);
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request) {
            if (! ($request->expectsJson() || $request->is('api/*'))) {
                return null;
            }

            return new JsonResponse([
                'error' => 'RESOURCE_NOT_FOUND',
                'message' => __('errors.NOT_FOUND'),
            ], 404);
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if (! ($request->expectsJson() || $request->is('api/*'))) {
                return null;
            }

            return new JsonResponse([
                'error' => 'FORBIDDEN',
                'message' => __('errors.FORBIDDEN'),
            ], 403);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! ($request->expectsJson() || $request->is('api/*'))) {
                return null;
            }

            if ($exception->getStatusCode() === 404) {
                return new JsonResponse([
                    'error' => 'RESOURCE_NOT_FOUND',
                    'message' => __('errors.NOT_FOUND'),
                ], 404);
            }

            return new JsonResponse([
                'error' => $exception->getMessage() ?: 'HTTP_ERROR',
                'message' => $exception->getMessage() ?: 'HTTP_ERROR',
            ], $exception->getStatusCode());
        });
    })->create();
