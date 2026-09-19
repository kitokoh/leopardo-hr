<?php

declare(strict_types=1);

namespace App\Modules\Communication\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Modules\Communication\Domain\Models\CommunicationIntegration;
use App\Modules\Communication\Domain\Support\CommunicationFeatures;
use App\Modules\Communication\Infrastructure\Services\GoogleGmailOAuthService;
use App\Modules\Communication\Infrastructure\Services\GoogleGmailSyncService;
use App\Modules\Communication\Interfaces\Api\V1\Controllers\Concerns\AssertsTenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Connexion Google par utilisateur (BC-29 COMMUNICATION, R1 #7686).
 *
 * Flow OAuth 2.0 COTE SERVEUR :
 *  1. POST /communication/integrations/google  (authentifie, tenant, module)
 *     -> pose un state anti-CSRF a usage unique en cache (10 min) qui porte
 *        l'identite du demandeur (employee_id + company_id), renvoie l'URL
 *        de consentement Google.
 *  2. GET /communication/integrations/google/callback (public : le navigateur
 *     revient de Google SANS bearer token)
 *     -> le state EST l'authentification : inconnu/deja consomme = 400 ;
 *        il re-verifie le feature flag du tenant (fail-closed), echange le
 *        code, stocke les tokens CHIFFRES (casts `encrypted` du modele).
 *  3. GET /communication/integrations : la liste de SES boites (jamais
 *     celles des autres — « aucun acces inter-boites sans assignation »).
 *  4. DELETE /communication/integrations/{integration} : revocation propre
 *     (cote Google + purge locale), policy owner/principal/rh.
 *
 * Le state vit en CACHE (pas en session) : la surface API v1 n'a pas de
 * middleware de session (leçon /auth/google, routes/api.php) et le callback
 * doit fonctionner sans cookie.
 */
class CommunicationIntegrationController extends Controller
{
    use AssertsTenantScope;

    private const STATE_CACHE_PREFIX = 'communication:oauth:state:';

    private const STATE_TTL_MINUTES = 10;

    public function __construct(
        private readonly GoogleGmailOAuthService $google,
        private readonly GoogleGmailSyncService $sync,
    ) {}

    /**
     * Boites connectees de l'employe COURANT uniquement (minimisation :
     * aucun token ne sort — `$hidden` + payload explicite).
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CommunicationIntegration::class);

        /** @var Employee $employee */
        $employee = $request->user();

        $integrations = CommunicationIntegration::query()
            ->where('employee_id', $employee->id)
            ->orderBy('provider')
            ->get()
            ->map(fn (CommunicationIntegration $integration): array => $this->present($integration));

        return new JsonResponse(['data' => $integrations->all()]);
    }

    /**
     * Demarre le flow OAuth Google : state anti-CSRF a usage unique + URL de
     * consentement. 503 si le serveur n'a pas ses variables d'env Google
     * (pattern GOOGLE_OAUTH_UNAVAILABLE #5170 — jamais d'URL a moitie construite).
     */
    public function connectGoogle(Request $request): JsonResponse
    {
        $this->authorize('create', CommunicationIntegration::class);

        if (! $this->google->isConfigured()) {
            Log::error('communication.google.not_configured', [
                'client_id' => filled(config('services.google.client_id')),
                'client_secret' => filled(config('services.google.client_secret')),
                'redirect' => filled(config('services.google.communication_redirect')),
            ]);

            return new JsonResponse([
                'error' => 'GOOGLE_OAUTH_UNAVAILABLE',
                'message' => 'GOOGLE_OAUTH_UNAVAILABLE',
            ], 503);
        }

        /** @var Employee $employee */
        $employee = $request->user();

        $state = Str::random(40);

        Cache::put(
            self::STATE_CACHE_PREFIX.$state,
            [
                'employee_id' => $employee->id,
                'company_id' => (string) $employee->company_id,
            ],
            now()->addMinutes(self::STATE_TTL_MINUTES)
        );

        return new JsonResponse([
            'data' => [
                'authorization_url' => $this->google->authorizationUrl($state),
                'expires_in' => self::STATE_TTL_MINUTES * 60,
            ],
        ]);
    }

    /**
     * Retour de Google (route PUBLIQUE — le state a usage unique est la seule
     * preuve d'identite ; consomme via Cache::pull, un rejeu = 400).
     */
    public function googleCallback(Request $request): JsonResponse
    {
        $state = $request->query('state');

        if (! is_string($state) || $state === '') {
            return new JsonResponse([
                'error' => 'OAUTH_STATE_INVALID',
                'message' => 'OAUTH_STATE_INVALID',
            ], 400);
        }

        /** @var array{employee_id: int, company_id: string}|null $context */
        $context = Cache::pull(self::STATE_CACHE_PREFIX.$state);

        if (! is_array($context)) {
            // State inconnu, expire ou deja consomme : pas de tentative
            // d'echange (anti-CSRF #2619 transpose au module).
            return new JsonResponse([
                'error' => 'OAUTH_STATE_INVALID',
                'message' => 'OAUTH_STATE_INVALID',
            ], 400);
        }

        // L'utilisateur a refuse le consentement (ou Google renvoie une
        // erreur) : rien n'est stocke.
        if (is_string($request->query('error'))) {
            return new JsonResponse([
                'error' => 'OAUTH_CONSENT_DENIED',
                'message' => 'OAUTH_CONSENT_DENIED',
            ], 400);
        }

        // Fail-closed : la route est publique, le gate module est re-verifie
        // a la main sur la company portee par le state (kill switch conserve).
        /** @var Company|null $company */
        $company = Company::query()->find($context['company_id']);

        if ($company === null || ! $company->hasFeature(CommunicationFeatures::COMMUNICATION)) {
            return new JsonResponse([
                'error' => 'FEATURE_NOT_ENABLED',
                'message' => 'FEATURE_NOT_ENABLED',
            ], 403);
        }

        $code = $request->query('code');

        if (! is_string($code) || $code === '') {
            return new JsonResponse([
                'error' => 'OAUTH_CODE_MISSING',
                'message' => 'OAUTH_CODE_MISSING',
            ], 400);
        }

        $tokens = $this->google->exchangeCode($code);

        if ($tokens === null) {
            return new JsonResponse([
                'error' => 'OAUTH_EXCHANGE_FAILED',
                'message' => 'OAUTH_EXCHANGE_FAILED',
            ], 502);
        }

        $email = $this->google->fetchAccountEmail($tokens['access_token']);

        // Hors surface tenant (pas de middleware `tenant` ici) : le scope
        // global ne s'applique pas, on borne la requete au tenant du state et
        // company_id est pose par forceFill (jamais par mass assignment #7646).
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
        Log::channel('audit')->info('communication.google.connected', [
            'company_id' => $context['company_id'],
            'employee_id' => $context['employee_id'],
            'integration_id' => $integration->id,
        ]);

        return new JsonResponse([
            'data' => [
                'status' => CommunicationIntegration::STATUS_ACTIVE,
                'provider' => CommunicationIntegration::PROVIDER_GOOGLE,
                'email' => $email,
            ],
        ]);
    }

    /**
     * Deconnexion = revocation cote Google + purge des tokens (statut
     * `revoked`) + PURGE COMPLETE des fils/messages synchronises (exigence
     * R2 #7687 : plus aucun corps de message en base apres deconnexion).
     * Binding implicite tenant-scope : une integration d'un autre tenant
     * est un 404 avant meme la policy.
     */
    public function destroy(Request $request, CommunicationIntegration $integration): JsonResponse
    {
        // Binding implicite resolu avant le middleware tenant : garde 404
        // explicite (une boite d'un autre tenant n'existe pas pour l'appelant).
        $this->assertTenantScope($request, $integration);
        $this->authorize('delete', $integration);

        $this->google->revoke($integration);

        // Purge R2 : threads + messages (corps chiffres compris) + curseurs
        // de sync — une reconnexion repart d'une full sync propre.
        $this->sync->purge($integration);

        Log::channel('audit')->info('communication.google.revoked', [
            'company_id' => $integration->company_id,
            'employee_id' => $integration->employee_id,
            'integration_id' => $integration->id,
        ]);

        return new JsonResponse([
            'data' => $this->present($integration->refresh()),
        ]);
    }

    /**
     * Representation API d'une integration : JAMAIS les tokens.
     *
     * @return array<string, mixed>
     */
    private function present(CommunicationIntegration $integration): array
    {
        return [
            'id' => $integration->id,
            'provider' => $integration->provider,
            'email' => $integration->email,
            'scopes' => $integration->scopes ?? [],
            'status' => $integration->status,
            'expires_at' => $integration->expires_at?->toIso8601String(),
            'connected_at' => $integration->connected_at?->toIso8601String(),
            'revoked_at' => $integration->revoked_at?->toIso8601String(),
        ];
    }
}
