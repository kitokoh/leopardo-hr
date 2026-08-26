<?php

use App\Core\Http\Middleware\HttpCacheMiddleware;
use App\Core\Http\Middleware\IdempotencyMiddleware;
use App\Exceptions\DomainException;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\ApiVersionMiddleware;
use App\Http\Middleware\AuthenticateZktecoDevice;
use App\Http\Middleware\Cameras\EnsureCameraModuleMiddleware;
use App\Http\Middleware\CompressResponse;
use App\Http\Middleware\EnsureApiManagerMiddleware;
use App\Http\Middleware\EnsureAppContextMiddleware;
use App\Http\Middleware\EnsureKioskSearchPathReset;
use App\Http\Middleware\PartnerLinkMiddleware;
use App\Http\Middleware\RequestIdMiddleware;
use App\Http\Middleware\RequireTenantCountry;
use App\Http\Middleware\ResilientThrottleRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SentryContextMiddleware;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\StructuredLogging;
use App\Http\Middleware\TenantMiddleware;
use App\Http\Middleware\TokenAutoRefreshMiddleware;
use App\Http\Middleware\Web\EnsureEmployeeMiddleware;
use App\Http\Middleware\Web\EnsureManagerMiddleware;
use App\Http\Middleware\Web\EnsureManagerRoleMiddleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
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
        $schedule->command('monitor:slow-queries --threshold=500')->everyFifteenMinutes();
        // Issue #4948 : trial provisionings bloqués (worker de queue jamais
        // exécuté) → fail-loud au lieu d'un pending silencieux.
        $schedule->command('trial-provisionings:sweep')->everyFifteenMinutes();
        // Plan 64 — Auto-close attendance logs without check-out after 12h
        $schedule->command('attendance:auto-close')->hourly();
        $schedule->command('accounting:purge-expired-shares')->daily();
        // PA2-PAY-012 — Nightly progressive payroll pre-calculation
        $schedule->command('payroll:precalculate')->dailyAt('02:00');
        // Audit Mobile+Edge 2026-07-26 (issue #1288) — Edge node silence /
        // license-expiry monitoring was implemented but never scheduled; a
        // silent/offline Edge node at a client site (or an expiring/expired
        // offline license) went completely unnoticed in production.
        //
        // `edge:detect-silent-nodes` remains available as a non-scheduled
        // compatibility command for legacy operational scripts/fixtures. It
        // detects the old node_id schema only; the canonical UUID model is
        // monitored by `edge:monitor` below. See issue #1291 and
        // docs/audits/AUDIT_MOBILE_EDGE_2026-07-26.md sections 1.3/1.4.
        $schedule->command('edge:monitor')->everyThirtyMinutes()->withoutOverlapping();
        // #R12 — Rappel d'onboarding J+1 : envoyé chaque jour à 09:00 UTC.
        // Cible les managers dont la société a été créée il y a 20h–28h et
        // dont l'onboarding comporte encore des étapes requises non complétées.
        $schedule->command('onboarding:send-reminders')->dailyAt('09:00');
    })
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Render sits as the single edge proxy in front of this app; without
        // trusting it explicitly, Illuminate\Http\Request::ip() falls back to
        // undefined behaviour for X-Forwarded-For, which weakens per-IP rate
        // limiting (RateLimiter::for('api', ...) etc). See
        // docs/security/AUDIT_API_2026-07-19.md, section 5.
        //
        // Issue #4494 : `at: '*'` trustait n'importe quel proxy — un client
        // internet direct pouvait forger X-Forwarded-For et changer d'IP à
        // chaque requête, contournant TOUS les limiteurs IP (auth-sensitive,
        // trial signup, public-careers, webhooks-inbound, kiosk-punch…).
        // On ne fait confiance qu'aux réseaux privés (loopback + RFC1918 +
        // ULA) : Render atteint l'app depuis son réseau privé, donc XFF y est
        // honoré ; un peer public hors liste → XFF ignoré → `$request->ip()`
        // reflète l'IP réelle. CIDR documenté dans render.yaml.
        $middleware->trustProxies(
            at: [
                '127.0.0.1',
                '::1',
                '10.0.0.0/8',
                '172.16.0.0/12',
                '192.168.0.0/16',
                'fc00::/7',
            ],
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
        );

        $middleware->api(prepend: [RequestIdMiddleware::class, ApiVersionMiddleware::class, SetLocale::class, StructuredLogging::class, SentryContextMiddleware::class, CompressResponse::class]);

        // RTMX (#5277) — socle plateforme temps réel / réseau faible :
        // GET conditionnels (ETag/304) + rejeu idempotent des écritures.
        // Append : sur la requête, ces middleware s'exécutent après les
        // prepend du groupe mais avant les middleware de route (auth:sanctum,
        // tenant) — l'IdempotencyMiddleware ne lit que le header Authorization
        // brut (jamais l'utilisateur résolu) ; en sortie, ils voient la réponse
        // AVANT CompressResponse → ETag calculé sur le corps non compressé
        // (stable quelle que soit l'encodage).
        $middleware->api(append: [
            IdempotencyMiddleware::class,
            HttpCacheMiddleware::class,
        ]);

        $middleware->web(append: [
            PartnerLinkMiddleware::class,
        ]);

        // Defence-in-depth security headers on every response (issue #1469).
        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            'tenant' => TenantMiddleware::class,
            'manager' => EnsureManagerMiddleware::class,
            'manager_role' => EnsureManagerRoleMiddleware::class,
            'employee' => EnsureEmployeeMiddleware::class,
            'module.cameras' => EnsureCameraModuleMiddleware::class,
            'admin' => AdminMiddleware::class,
            'api.manager' => EnsureApiManagerMiddleware::class,
            'app.context' => EnsureAppContextMiddleware::class,
            'token.refresh' => TokenAutoRefreshMiddleware::class,
            // MULTI-PAYS (#1867) : pays légal du tenant obligatoire et supporté
            // avant toute opération RH/paie sensible.
            'tenant.country' => RequireTenantCountry::class,
            // #3368 : restaure le search_path après chaque requête kiosque
            // (les handlers basculent vers le schéma tenant sans try/finally).
            'kiosk.search_path' => EnsureKioskSearchPathReset::class,
            // #4934 (audit web client 2026-08-17) : auth device ZKTeco
            // (heartbeat / sync-attendance) — fail-closed, search_path-safe.
            'zkteco.device' => AuthenticateZktecoDevice::class,
            // Issue #1774 : variante résiliente du middleware de throttling —
            // un échec du stockage du compteur répond 429 dégradé (au lieu d'un
            // 500) et les exceptions du pipeline en aval ne sont jamais masquées.
            'throttle' => ResilientThrottleRequests::class,
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
            $catalogHasCode = $translatedMessage !== 'errors.'.$errorCode;

            // #4171 : quand le code n'est pas au catalogue, le message brut de
            // l'exception (français interne, détails de service) ne doit
            // JAMAIS fuiter vers le client — réponse générique localisée +
            // trace serveur pour le diagnostic.
            if (! $catalogHasCode) {
                report($exception);
            }

            $message = $catalogHasCode
                ? $translatedMessage
                : __('errors.SERVER_ERROR');

            return new JsonResponse([
                'error' => $errorCode,
                'message' => $errorCode,
                'localized_message' => $message,
            ], $exception->statusCode());
        });

        // 429 : ThrottleRequestsException (quota dépassé) — réponse API
        // structurée + localisée (#4955), même contrat que les autres erreurs.
        $exceptions->render(function (ThrottleRequestsException $exception, Request $request) {
            if (! ($request->expectsJson() || $request->is('api/*'))) {
                return null;
            }

            $response = new JsonResponse([
                'error' => 'TOO_MANY_REQUESTS',
                'message' => 'TOO_MANY_REQUESTS',
                'localized_message' => __('errors.TOO_MANY_REQUESTS'),
            ], 429);

            // Issue #1774 / #5034 : préserver les headers du throttling
            // (Retry-After, X-RateLimit-*) posés par ThrottleRequestsException —
            // sans eux, le 429 est inexploitable côté client (retry).
            foreach ($exception->getHeaders() as $headerName => $headerValue) {
                $response->headers->set($headerName, $headerValue);
            }

            return $response;
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
                'localized_message' => __('errors.FILE_TOO_LARGE'),
                'errors' => [
                    'file' => [__('errors.FILE_TOO_LARGE')],
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

            $statusCode = $exception->getStatusCode();

            // Issue #3810 : ne jamais exposer de message brut issu d'une
            // exception interne (SQLSTATE, chemins serveur, traces) dans les
            // réponses JSON. Les messages statiques passés à abort() par les
            // contrôleurs (codes stables ou textes localisés volontaires)
            // restent exposés ; tout message à signature interne est remplacé
            // par un code stable + message générique localisé, et le détail
            // est conservé côté serveur (logs).
            // #4689 (audit 360° 2026-08-16) : code stable mappé par statut —
            // utilisé par les deux branches (leak → remplacement complet,
            // message statique → localized_message systématique).
            $code = match ($statusCode) {
                400 => 'BAD_REQUEST',
                401 => 'UNAUTHENTICATED',
                403 => 'FORBIDDEN',
                409 => 'CONFLICT',
                422 => 'VALIDATION_FAILED',
                429 => 'TOO_MANY_REQUESTS',
                500 => 'SERVER_ERROR',
                503 => 'SERVICE_UNAVAILABLE',
                default => 'HTTP_ERROR',
            };
            $rawMessage = (string) $exception->getMessage();
            $leakSignature = preg_match(
                '/SQLSTATE|PDOException|QueryException|RuntimeException|ErrorException|TypeError|'
                .'\\/var\\/www|vendor\\/laravel|getMessage\\(\\)|stack trace|#[0-9]+ \\/|\.php on line|'
                .'\.php:[0-9]+/i',
                $rawMessage
            );

            // #4955 (audit web client 2026-08-17) : le 429 de ThrottleRequests
            // porte toujours le message Laravel brut ("Too Many Attempts.") qui
            // n'est PAS un message statique volontaire. On sert systématiquement
            // le code stable + message localisé (headers Retry-After conservés).
            if ($statusCode === 429 || $leakSignature === 1 || trim($rawMessage) === '') {

                Log::warning('HTTP exception rendered with sanitized message (issue #3810)', [
                    'status' => $statusCode,
                    'code' => $code,
                    'exception' => get_class($exception),
                    'message' => $rawMessage,
                    'url' => $request->fullUrl(),
                ]);

                $response = new JsonResponse([
                    'error' => $code,
                    'message' => $code,
                    'localized_message' => __("errors.{$code}", [], $request->getLocale()),
                ], $statusCode);

                // Issue #1774 : préserver les headers du throttling
                // (Retry-After, X-RateLimit-*) posés par ThrottleRequestsException —
                // sans eux, le 429 dégradé/limite est inexploitable côté client.
                foreach ($exception->getHeaders() as $headerName => $headerValue) {
                    /** @var array<string>|string|null $headerValue */
                    $response->headers->set($headerName, $headerValue);
                }

                return $response;
            }

            $response = new JsonResponse([
                'error' => $rawMessage ?: 'HTTP_ERROR',
                'message' => $rawMessage ?: 'HTTP_ERROR',
                // #4689 (audit 360° 2026-08-16) : toute réponse d'erreur HTTP
                // porte désormais localized_message (forme standard API) —
                // error/message gardent le contrat existant (messages
                // statiques volontaires exposés, cf. #3810).
                'localized_message' => __("errors.{$code}", [], $request->getLocale()),
            ], $statusCode);

            // Issue #1774 : préserver les headers du throttling
            // (Retry-After, X-RateLimit-*) posés par ThrottleRequestsException —
            // sans eux, le 429 dégradé/limite est inexploitable côté client.
            foreach ($exception->getHeaders() as $headerName => $headerValue) {
                /** @var array<string>|string|null $headerValue */
                $response->headers->set($headerName, $headerValue);
            }

            return $response;
        });

        // QA 2026-08-15 (#2653) : une requête non authentifiée sur /api/*
        // doit répondre 401 JSON conforme au contrat, quel que soit le client
        // (avec ou sans header `Accept: application/json`). Sans ce renderer,
        // Laravel redirige vers /login en HTML — inutilisable pour kiosk,
        // edge et scripts.
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! ($request->expectsJson() || $request->is('api/*'))) {
                return null;
            }

            return new JsonResponse([
                'error' => 'UNAUTHENTICATED',
                'message' => 'UNAUTHENTICATED',
                'localized_message' => __('errors.UNAUTHENTICATED'),
            ], 401);
        });

        // QA 2026-08-15 (#2653) : dernier filet — toute exception non mappée
        // sur /api/* rend un 500 conforme au contrat (error/localized_message),
        // loggué et remonté à Sentry, sans jamais exposer le message interne.
        // Enregistré en dernier pour laisser les renderers spécifiques gagner.
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! ($request->expectsJson() || $request->is('api/*'))) {
                return null;
            }

            report($exception);

            return new JsonResponse([
                'error' => 'INTERNAL_ERROR',
                'message' => 'INTERNAL_ERROR',
                'localized_message' => __('errors.SERVER_ERROR'),
            ], 500);
        });
    })->create();
