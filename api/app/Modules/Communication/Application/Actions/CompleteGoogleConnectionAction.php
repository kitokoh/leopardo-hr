<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Communication\Domain\Models\CommunicationIntegration;
use App\Modules\Communication\Domain\Support\CommunicationFeatures;
use App\Modules\Communication\Infrastructure\Services\GoogleGmailOAuthService;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Log\LogManager;

/**
 * Cas d'usage « finaliser la connexion Google » (BC-29 COMMUNICATION,
 * R1 #7686) — retour du navigateur sur la route PUBLIQUE de callback :
 * le state à usage unique EST l'authentification (inconnu/déjà consommé
 * = 400, rejeu impossible), le feature flag du tenant est re-vérifié
 * (fail-closed, kill switch conservé), le code est échangé et les tokens
 * stockés CHIFFRÉS (casts `encrypted` du modèle).
 *
 * Extrait de `CommunicationIntegrationController::googleCallback`
 * (BOS-024e, #8216). Hors surface tenant (pas de middleware `tenant`
 * ici) : le scope global ne s'applique pas, la requête est bornée au
 * tenant du state et `company_id` est posé par `forceFill` (jamais par
 * mass assignment #7646). Audit sans PII sensible : jamais de token,
 * jamais de payload Google. Cache et logs injectés via contrats
 * (garde #6568 — pas de facades ici).
 */
final class CompleteGoogleConnectionAction
{
    public function __construct(
        private readonly GoogleGmailOAuthService $google,
        private readonly CacheRepository $cache,
        private readonly LogManager $logs,
    ) {}

    /**
     * @param  mixed  $state  Paramètre query `state` (brut).
     * @param  mixed  $consentError  Paramètre query `error` (brut).
     * @param  mixed  $code  Paramètre query `code` (brut).
     * @return array{status: string, provider: string, email: string|null}
     *
     * @throws HttpResponseException 400 OAUTH_STATE_INVALID /
     *                               OAUTH_CONSENT_DENIED / OAUTH_CODE_MISSING · 403
     *                               FEATURE_NOT_ENABLED · 502 OAUTH_EXCHANGE_FAILED
     */
    public function execute(mixed $state, mixed $consentError, mixed $code): array
    {
        if (! is_string($state) || $state === '') {
            throw self::invalidState();
        }

        /** @var array{employee_id: int, company_id: string}|null $context */
        // tenant-cache:shared — consommation du state (endpoint public, pas de contexte tenant) (#8058)
        $context = $this->cache->pull(StartGoogleConnectionAction::STATE_CACHE_PREFIX.$state);

        if (! is_array($context)) {
            // State inconnu, expiré ou déjà consommé : pas de tentative
            // d'échange (anti-CSRF #2619 transposé au module).
            throw self::invalidState();
        }

        // L'utilisateur a refusé le consentement (ou Google renvoie une
        // erreur) : rien n'est stocké.
        if (is_string($consentError)) {
            throw new HttpResponseException(new JsonResponse([
                'error' => 'OAUTH_CONSENT_DENIED',
                'message' => 'OAUTH_CONSENT_DENIED',
            ], 400));
        }

        // Fail-closed : la route est publique, le gate module est
        // re-vérifié à la main sur la company portée par le state.
        /** @var Company|null $company */
        $company = Company::query()->find($context['company_id']);

        if ($company === null || ! $company->hasFeature(CommunicationFeatures::COMMUNICATION)) {
            throw new HttpResponseException(new JsonResponse([
                'error' => 'FEATURE_NOT_ENABLED',
                'message' => 'FEATURE_NOT_ENABLED',
            ], 403));
        }

        if (! is_string($code) || $code === '') {
            throw new HttpResponseException(new JsonResponse([
                'error' => 'OAUTH_CODE_MISSING',
                'message' => 'OAUTH_CODE_MISSING',
            ], 400));
        }

        $tokens = $this->google->exchangeCode($code);

        if ($tokens === null) {
            throw new HttpResponseException(new JsonResponse([
                'error' => 'OAUTH_EXCHANGE_FAILED',
                'message' => 'OAUTH_EXCHANGE_FAILED',
            ], 502));
        }

        $email = $this->google->fetchAccountEmail($tokens['access_token']);

        /** @var CommunicationIntegration $integration */
        $integration = CommunicationIntegration::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $context['company_id'])
            ->where('employee_id', $context['employee_id'])
            ->where('provider', CommunicationIntegration::PROVIDER_GOOGLE)
            ->firstOrNew([]);

        $integration->forceFill([
            'company_id' => $context['company_id'],
            'employee_id' => $context['employee_id'],
            'provider' => CommunicationIntegration::PROVIDER_GOOGLE,
            'email' => $email,
            'scopes' => $tokens['scopes'],
            'access_token' => $tokens['access_token'],
            // Reconnexion sans nouveau refresh_token : on garde l'ancien.
            'refresh_token' => $tokens['refresh_token'] ?? $integration->refresh_token,
            'expires_at' => now()->addSeconds($tokens['expires_in']),
            'status' => CommunicationIntegration::STATUS_ACTIVE,
            'connected_at' => now(),
            'revoked_at' => null,
            'last_error' => null,
        ])->save();

        // Audit sans PII sensible : jamais de token, jamais de payload Google.
        $this->logs->channel('audit')->info('communication.google.connected', [
            'company_id' => $context['company_id'],
            'employee_id' => $context['employee_id'],
            'integration_id' => $integration->id,
        ]);

        return [
            'status' => CommunicationIntegration::STATUS_ACTIVE,
            'provider' => CommunicationIntegration::PROVIDER_GOOGLE,
            'email' => $email,
        ];
    }

    private static function invalidState(): HttpResponseException
    {
        return new HttpResponseException(new JsonResponse([
            'error' => 'OAUTH_STATE_INVALID',
            'message' => 'OAUTH_STATE_INVALID',
        ], 400));
    }
}
