<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Core\Feature\Infrastructure\Services\FeatureKillSwitchService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * MAT-010 (#5868) — feature flags & kill switch (super-admin).
 *
 * Interrupteur global par feature/module : `POST` active (stop du module pour
 * toute la plateforme, fail-closed, sans suppression de données), `DELETE`
 * désactive. Toute bascule est idempotente, horodatée et tracée
 * (`feature_kill_switches.toggled_by/toggled_at/reason` + canal d'audit).
 *
 * Réservé au super-admin (groupe `auth:super_admin_api`) — jamais exposé à
 * l'espace tenant.
 */
class PlatformFeatureKillSwitchController extends Controller
{
    public function __construct(private readonly FeatureKillSwitchService $killSwitches)
    {
    }

    public function index(): JsonResponse
    {
        return new JsonResponse(['data' => $this->killSwitches->list()]);
    }

    public function activate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'feature_key' => ['required', 'string', 'max:64'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $featureKey = (string) $validated['feature_key'];
        $reason = isset($validated['reason']) ? (string) $validated['reason'] : '';

        $this->killSwitches->kill($featureKey, $reason, $this->actorId($request));

        return new JsonResponse([
            'data' => [
                'feature_key' => $featureKey,
                'is_active' => true,
            ],
        ]);
    }

    public function deactivate(Request $request, string $key): JsonResponse
    {
        $this->killSwitches->revive($key, $this->actorId($request));

        return new JsonResponse([
            'data' => [
                'feature_key' => $key,
                'is_active' => false,
            ],
        ]);
    }

    private function actorId(Request $request): ?string
    {
        $identifier = $request->user()?->getAuthIdentifier();

        return $identifier !== null ? (string) $identifier : null;
    }
}
