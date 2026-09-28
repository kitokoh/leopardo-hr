<?php

declare(strict_types=1);

namespace App\Modules\Communication\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Communication\Application\Actions\CompleteGoogleConnectionAction;
use App\Modules\Communication\Application\Actions\RevokeIntegrationAction;
use App\Modules\Communication\Application\Actions\StartGoogleConnectionAction;
use App\Modules\Communication\Domain\Models\CommunicationIntegration;
use App\Modules\Communication\Interfaces\Api\V1\Controllers\Concerns\AssertsTenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function __construct(
        private readonly StartGoogleConnectionAction $startAction,
        private readonly CompleteGoogleConnectionAction $completeAction,
        private readonly RevokeIntegrationAction $revokeAction,
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

        /** @var Employee $employee */
        $employee = $request->user();

        // Délégation du cas d'usage (BOS-024e, #8216) : 503
        // GOOGLE_OAUTH_UNAVAILABLE, state anti-CSRF à usage unique en cache
        // (10 min), scopes gmail.send/compose demandés à la connexion —
        // contrat inchangé.
        $payload = $this->startAction->execute($employee, $request->boolean('with_send'));

        return new JsonResponse(['data' => $payload]);
    }

    /**
     * Retour de Google (route PUBLIQUE — le state a usage unique est la seule
     * preuve d'identite ; consomme via Cache::pull, un rejeu = 400).
     */
    public function googleCallback(Request $request): JsonResponse
    {
        // Délégation du cas d'usage (BOS-024e, #8216) : state à usage
        // unique = seule preuve d'identité (400), consentement refusé
        // (400), gate module re-vérifié fail-closed (403), échange du code
        // (502), tokens chiffrés stockés — contrat inchangé.
        $payload = $this->completeAction->execute(
            $request->query('state'),
            $request->query('error'),
            $request->query('code'),
        );

        return new JsonResponse(['data' => $payload]);
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

        // Délégation du cas d'usage (BOS-024e, #8216) : révocation Google +
        // purge complète fils/messages/curseurs (R2) + audit — contrat
        // inchangé.
        $integration = $this->revokeAction->execute($integration);

        return new JsonResponse([
            'data' => $this->present($integration),
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
