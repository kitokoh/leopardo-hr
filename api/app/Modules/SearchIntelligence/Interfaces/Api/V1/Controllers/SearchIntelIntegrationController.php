<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\SearchIntelligence\Domain\Enums\IntegrationStatus;
use App\Modules\SearchIntelligence\Domain\Models\Integration;
use App\Modules\SearchIntelligence\Infrastructure\Services\IntegrationLifecycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * GSI-8 (#8519) — cycle de vie des connecteurs externes.
 *
 * Routes (toutes sous `platform.permission:*`, cf. routes/modules/search_intelligence.php) :
 *
 *   GET    /api/v1/platform/search-intel/integrations            → search_intel.view
 *   PUT    /api/v1/platform/search-intel/integrations/{provider} → search_intel.manage
 *   POST   /api/v1/platform/search-intel/integrations/{provider}/test → search_intel.manage
 *   DELETE /api/v1/platform/search-intel/integrations/{provider} → search_intel.manage
 *
 * L'issue #8519 ne spécifiait que la lecture : un écran qui affiche quatre
 * connecteurs « non connectés » sans permettre de les configurer n'est pas une
 * fonctionnalité. Les trois mutations manquantes sont ajoutées ici.
 *
 * RÈGLE ABSOLUE : aucun secret ne sort de ce contrôleur. Toutes les réponses
 * passent par `IntegrationLifecycle::toPublicArray()`, qui n'expose qu'un
 * booléen `has_credentials`.
 */
final class SearchIntelIntegrationController
{
    public function __construct(private readonly IntegrationLifecycle $lifecycle) {}

    public function index(): JsonResponse
    {
        /** @var array<string, Integration> $byProvider */
        $byProvider = Integration::query()->get()->keyBy('provider')->all();

        $rows = [];

        foreach (IntegrationLifecycle::PROVIDERS as $provider) {
            $rows[] = $this->lifecycle->toPublicArray($provider, $byProvider[$provider] ?? null);
        }

        return new JsonResponse(['data' => $rows]);
    }

    /**
     * Enregistre les identifiants d'un fournisseur (chiffrés au repos).
     *
     * Le jeu d'identifiants est REMPLACÉ, pas fusionné : voir
     * `IntegrationLifecycle::configure()`. Une clé inconnue est rejetée en 422
     * pour éviter de persister un champ que personne ne lira.
     */
    public function configure(string $provider, Request $request): JsonResponse
    {
        if (! IntegrationLifecycle::isSupportedProvider($provider)) {
            return new JsonResponse([
                'error' => 'UNSUPPORTED_PROVIDER',
                'message' => "Fournisseur non supporté : {$provider}.",
                'supported' => IntegrationLifecycle::PROVIDERS,
            ], 404);
        }

        $validated = $request->validate([
            'credentials' => ['required', 'array', 'min:1'],
        ]);

        try {
            $integration = $this->lifecycle->configure(
                $provider,
                $validated['credentials'],
                $this->platformAdminId($request),
            );
        } catch (InvalidArgumentException $exception) {
            return new JsonResponse([
                'error' => 'INVALID_CREDENTIALS',
                'message' => $exception->getMessage(),
                'expected_keys' => IntegrationLifecycle::credentialKeysFor($provider),
            ], 422);
        }

        return new JsonResponse([
            'data' => $this->lifecycle->toPublicArray($provider, $integration),
        ]);
    }

    /**
     * Teste un connecteur — honnêtement.
     *
     * Sans appel réel aux API du fournisseur, un succès ne prouve que la
     * complétude des champs : l'état reste `not_connected` avec un message
     * explicite de finalisation d'autorisation. Jamais de faux « connecté ».
     */
    public function test(string $provider, Request $request): JsonResponse
    {
        if (! IntegrationLifecycle::isSupportedProvider($provider)) {
            return new JsonResponse([
                'error' => 'UNSUPPORTED_PROVIDER',
                'message' => "Fournisseur non supporté : {$provider}.",
                'supported' => IntegrationLifecycle::PROVIDERS,
            ], 404);
        }

        $result = $this->lifecycle->test($provider, $this->platformAdminId($request));

        /** @var Integration|null $integration */
        $integration = Integration::query()->where('provider', $provider)->first();

        return new JsonResponse([
            'data' => $this->lifecycle->toPublicArray($provider, $integration),
            'result' => [
                'status' => $result['status']->value,
                'message' => $result['message'],
                // Explicitement exposé pour que l'UI ne puisse pas prétendre
                // que l'autorisation fournisseur a été vérifiée.
                'provider_authorization_verified' => $result['status'] === IntegrationStatus::Connected,
            ],
        ]);
    }

    /**
     * Révoque un connecteur : les identifiants sont EFFACÉS, pas seulement
     * marqués. Conserver un secret après révocation serait une fuite dormante.
     */
    public function revoke(string $provider, Request $request): JsonResponse
    {
        if (! IntegrationLifecycle::isSupportedProvider($provider)) {
            return new JsonResponse([
                'error' => 'UNSUPPORTED_PROVIDER',
                'message' => "Fournisseur non supporté : {$provider}.",
                'supported' => IntegrationLifecycle::PROVIDERS,
            ], 404);
        }

        $integration = $this->lifecycle->revoke($provider, $this->platformAdminId($request));

        return new JsonResponse([
            'data' => $this->lifecycle->toPublicArray($provider, $integration),
        ]);
    }

    /**
     * Identifiant du compte PLATEFORME appelant.
     *
     * Les routes sont derrière `auth:super_admin_api` : `Auth::id()` interroge
     * le guard par défaut (`sanctum` → `users`) et renverrait `null` (ou l'id
     * d'un utilisateur tenant homonyme). On résout donc explicitement le guard
     * plateforme — la même erreur a été corrigée sur `/platform/growth`
     * (#8523 point 7, PR #8905).
     */
    private function platformAdminId(Request $request): ?int
    {
        $admin = $request->user('super_admin_api');

        if (! $admin instanceof SuperAdmin) {
            return null;
        }

        $id = $admin->getKey();

        return is_int($id) ? $id : (is_numeric($id) ? (int) $id : null);
    }
}
