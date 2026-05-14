<?php

use App\Exceptions\DomainException;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\Cameras\EnsureCameraModuleMiddleware;
use App\Http\Middleware\RequestIdMiddleware;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\StructuredLogging;
use App\Http\Middleware\TenantMiddleware;
use App\Http\Middleware\Web\EnsureEmployeeMiddleware;
use App\Http\Middleware\Web\EnsureManagerMiddleware;
use App\Http\Middleware\Web\EnsureManagerRoleMiddleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('leave:accrue')->daily();
        $schedule->command('leave:carry-forward')->yearlyOn(1, 1, '02:00');
        $schedule->command('contracts:alert-expiring')->daily();
        $schedule->command('billing:check-trials')->daily();
        $schedule->command('billing:check-overdue')->daily();
        $schedule->command('billing:generate-invoices')->monthlyOn(1, '03:00');
    })
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [RequestIdMiddleware::class, SetLocale::class, StructuredLogging::class]);

        $middleware->alias([
            'tenant' => TenantMiddleware::class,
            'manager' => EnsureManagerMiddleware::class,
            'manager_role' => EnsureManagerRoleMiddleware::class,
            'employee' => EnsureEmployeeMiddleware::class,
            'module.cameras' => EnsureCameraModuleMiddleware::class,
            'admin' => AdminMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        Integration::handles($exceptions);

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
                'message' => $errorCode,
                'localized_message' => $message,
            ], $exception->statusCode());
        });

        // Handle oversized file uploads gracefully (PHP rejects them before Laravel
        // validation runs, so we must catch PostTooLargeException explicitly).
        $exceptions->render(function (PostTooLargeException $exception, Request $request) {
            if (! ($request->expectsJson() || $request->is('api/*'))) {
                return null;
            }

            return new JsonResponse([
                'error' => 'VALIDATION_ERROR',
                'message' => 'VALIDATION_ERROR',
                'localized_message' => 'Le fichier envoyé dépasse la taille maximale autorisée.',
                'errors' => [
                    'file' => ['Le fichier dépasse la taille maximale autorisée.'],
                ],
            ], 422);
        });

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! ($request->expectsJson() || $request->is('api/*'))) {
                return null;
            }

            return new JsonResponse([
                'error' => 'VALIDATION_ERROR',
                'message' => 'VALIDATION_ERROR',
                'localized_message' => __('errors.VALIDATION_ERROR'),
                'errors' => $exception->errors(),
            ], 422);
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request) {
            if (! ($request->expectsJson() || $request->is('api/*'))) {
                return null;
            }

            return new JsonResponse([
                'error' => 'RESOURCE_NOT_FOUND',
                'message' => 'RESOURCE_NOT_FOUND',
                'localized_message' => __('errors.NOT_FOUND'),
            ], 404);
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if (! ($request->expectsJson() || $request->is('api/*'))) {
                return null;
            }

            return new JsonResponse([
                'error' => 'FORBIDDEN',
                'message' => 'FORBIDDEN',
                'localized_message' => __('errors.FORBIDDEN'),
            ], 403);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! ($request->expectsJson() || $request->is('api/*'))) {
                return null;
            }

            if ($exception->getStatusCode() === 404) {
                return new JsonResponse([
                    'error' => 'RESOURCE_NOT_FOUND',
                    'message' => 'RESOURCE_NOT_FOUND',
                    'localized_message' => __('errors.NOT_FOUND'),
                ], 404);
            }

            return new JsonResponse([
                'error' => $exception->getMessage() ?: 'HTTP_ERROR',
                'message' => $exception->getMessage() ?: 'HTTP_ERROR',
            ], $exception->getStatusCode());
        });
    })->create();
