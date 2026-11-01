<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Shared\Services\PublicCommerce\PlatformSettingsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * COULOIR 16 — M1 · réglage du seuil de catégories de la découverte publique.
 *
 * ## Pourquoi ce contrôleur existe
 *
 * Le seuil (« une catégorie n'apparaît que si la ville compte au moins N Pages
 * publiées dedans ») était LISIBLE depuis le M0 (`PlatformSettingsRepository`)
 * mais non ÉDITABLE : le changer exigeait un accès SQL à `public.platform_settings`.
 * Le cahier des charges impose « seuil réglable depuis le Superadmin ». Ce
 * contrôleur ferme cet écart, sans introduire de nouveau concept de réglages :
 * il réutilise le dépôt existant et la table existante.
 *
 * ## Trois décisions de sécurité
 *
 * 1. **Permission `settings.manage`** — la même que les autres réglages de
 *    plateforme (`/platform/ai/settings`). Un super admin chargé du support
 *    n'hérite pas du droit de changer le seuil commercial.
 * 2. **Validation stricte, jamais implicitement corrigée** — `min:1` + `integer`.
 *    Une valeur 0 est REFUSÉE (422), pas silencieusement remplacée par 5 : un
 *    super admin qui tape 0 doit savoir que 0 est invalide, sinon il croit avoir
 *    réglé quelque chose.
 * 3. **La réponse dit l'ancienne ET la nouvelle valeur** — l'écran affiche le
 *    changement et le journal d'audit le conserve. Une modification de seuil qui
 *    ne laisserait aucune trace exploitable serait indétectable.
 */
class PlatformDiscoverySettingsController extends Controller
{
    /** Borne haute : au-delà, aucune catégorie ne peut être atteinte. */
    private const MAX_CATEGORY_MIN_PAGES = 1000;

    public function __construct(
        private readonly PlatformSettingsRepository $settings,
    ) {}

    /**
     * État courant du réglage.
     *
     * `by_city` est renvoyé : un seuil par ville posé hors interface doit être
     * visible, sinon l'écran afficherait 5 alors que Paris tourne à 8.
     */
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => [
                'key' => PlatformSettingsRepository::DISCOVERY_CATEGORY_MIN_PAGES,
                'settings' => $this->settings->editableSettings(),
                'effective' => $this->settings->categoryMinPages(),
                'default' => PlatformSettingsRepository::DEFAULT_CATEGORY_MIN_PAGES,
                // Le minimum accepté est exposé pour que le formulaire affiche
                // la même règle que celle appliquée côté serveur — plutôt que
                // de la redécouvrir en recevant un 422.
                'min' => 1,
                'max' => self::MAX_CATEGORY_MIN_PAGES,
            ],
        ]);
    }

    /**
     * Écrit le seuil.
     *
     * `PUT` (idempotent) plutôt que `POST` : rejouer la même valeur ne doit
     * rien produire — ni écriture, ni ligne d'audit. C'est ce que garantit
     * `putCategoryMinPages()` en comparant à la valeur courante.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // `integer` refuse « 5.5 » et « abc » ; `min:1` refuse 0 et les
            // négatifs. Les deux sont nécessaires : `min:1` seul laisserait
            // passer 5.5, et `integer` seul laisserait passer 0.
            'category_min_pages' => ['required', 'integer', 'min:1', 'max:'.self::MAX_CATEGORY_MIN_PAGES],
        ]);

        /** @var int $value */
        $value = (int) $validated['category_min_pages'];

        try {
            $result = $this->settings->putCategoryMinPages($value, $this->actorId($request));
        } catch (\RuntimeException $exception) {
            if ($exception->getMessage() === 'PLATFORM_SETTINGS_TABLE_MISSING') {
                // La table n'existe pas encore (déploiement partiel). Répondre
                // 503 est honnête : ce n'est pas une erreur de la requête, et
                // la découverte publique continue de servir le défaut.
                return response()->json([
                    'error' => 'SETTINGS_UNAVAILABLE',
                    'message' => __('platform.discovery_settings_unavailable'),
                ], 503);
            }

            throw $exception;
        }

        return response()->json([
            'data' => [
                'changed' => $result['changed'],
                'previous' => $result['previous'],
                'current' => $result['current'],
            ],
        ]);
    }

    /**
     * Id du super admin appelant, pour `updated_by`.
     *
     * On lit l'id sur l'utilisateur authentifié par Sanctum : le corps de la
     * requête n'est JAMAIS consulté pour cette valeur. L'accepter du client
     * permettrait d'attribuer un changement à un autre administrateur.
     */
    private function actorId(Request $request): ?int
    {
        $id = $request->user()?->getKey();

        return is_int($id) ? $id : (is_numeric($id) ? (int) $id : null);
    }
}
