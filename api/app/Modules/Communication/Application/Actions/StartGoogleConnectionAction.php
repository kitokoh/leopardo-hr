<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Communication\Infrastructure\Services\GoogleGmailOAuthService;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;

/**
 * Cas d'usage « démarrer la connexion Google » (BC-29 COMMUNICATION,
 * R1 #7686) : state anti-CSRF à usage unique en cache (10 min) portant
 * l'identité du demandeur + URL de consentement.
 *
 * Extrait de `CommunicationIntegrationController::connectGoogle`
 * (BOS-024e, #8216). 503 `GOOGLE_OAUTH_UNAVAILABLE` si le serveur n'a pas
 * ses variables d'env Google (pattern #5170 — jamais d'URL à moitié
 * construite). Le scope `gmail.send` (+ `gmail.compose`, R4/R5) est
 * demandé à la connexion quand `withSend` — consentement incrémental.
 * Le state vit en CACHE (pas en session : la surface API v1 n'a pas de
 * middleware de session et le callback doit fonctionner sans cookie).
 * La Policy `create` reste au niveau interface ; le cache et le logger
 * sont injectés via contrats (garde #6568 — pas de facades ici).
 */
final class StartGoogleConnectionAction
{
    public const STATE_CACHE_PREFIX = 'communication:oauth:state:';

    public const STATE_TTL_MINUTES = 10;

    public function __construct(
        private readonly GoogleGmailOAuthService $google,
        private readonly CacheRepository $cache,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @return array{authorization_url: string, expires_in: int}
     *
     * @throws HttpResponseException 503 GOOGLE_OAUTH_UNAVAILABLE
     */
    public function execute(Employee $employee, bool $withSend): array
    {
        if (! $this->google->isConfigured()) {
            $this->logger->error('communication.google.not_configured', [
                'client_id' => filled(config('services.google.client_id')),
                'client_secret' => filled(config('services.google.client_secret')),
                'redirect' => filled(config('services.google.communication_redirect')),
            ]);

            throw new HttpResponseException(new JsonResponse([
                'error' => 'GOOGLE_OAUTH_UNAVAILABLE',
                'message' => 'GOOGLE_OAUTH_UNAVAILABLE',
            ], 503));
        }

        $scopes = GoogleGmailOAuthService::DEFAULT_SCOPES;

        if ($withSend) {
            $scopes[] = GoogleGmailOAuthService::GMAIL_SEND_SCOPE;
            $scopes[] = GoogleGmailOAuthService::GMAIL_COMPOSE_SCOPE;
        }

        $state = Str::random(40);

        // tenant-cache:shared — état OAuth aléatoire (40 chars), usage unique, company re-validée au callback (#8058)
        $this->cache->put(
            self::STATE_CACHE_PREFIX.$state,
            [
                'employee_id' => $employee->id,
                'company_id' => (string) $employee->company_id,
            ],
            now()->addMinutes(self::STATE_TTL_MINUTES)
        );

        return [
            'authorization_url' => $this->google->authorizationUrl($state, $scopes),
            'expires_in' => self::STATE_TTL_MINUTES * 60,
        ];
    }
}
