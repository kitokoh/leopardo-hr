<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Application\Actions\PlatformStackSwitchAction;
use App\Modules\Platform\Infrastructure\Services\StackRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * #8673 (épic #8670) — pilotage de l'infrastructure depuis le Super Admin.
 *
 * GET  /api/v1/platform/infra/stacks      → état des stacks dev + prod
 * POST /api/v1/platform/infra/stack-switch → bascule d'un tier
 *
 * Les deux routes sont gardées par `platform.permission:infra.manage`
 * (super_admin + ops). La bascule elle-même est déléguée à GitHub : voir
 * PlatformStackSwitchAction.
 */
class PlatformInfraController extends Controller
{
    public function stacks(StackRegistryService $registry): JsonResponse
    {
        return new JsonResponse([
            'data' => $registry->all(),
            // L'écran utilise ce booléen pour désactiver le bouton plutôt que
            // de laisser l'opérateur découvrir un 503 après confirmation.
            'dispatch_configured' => (string) config('services.github_stack_switch.token', '') !== '',
        ]);
    }

    public function switch(Request $request, PlatformStackSwitchAction $action): JsonResponse
    {
        $validated = $request->validate([
            // Le tier est explicite : la prod est le défaut côté écran, mais
            // une bascule dev ne doit jamais être déclenchée « par accident »
            // en croyant piloter la prod.
            'tier' => ['required', 'string', 'in:prod,dev'],
            'stack' => ['required', 'string', 'max:8', 'regex:/^[a-z0-9]+$/'],
            // La confirmation est une garde d'interface : elle évite le clic
            // malheureux, elle ne remplace pas la permission.
            'confirm' => ['required', 'accepted'],
        ]);

        $actor = $request->user('super_admin_api');
        $actorId = $actor !== null && isset($actor->id) ? (int) $actor->id : null;

        try {
            $result = $action->execute($validated['tier'], $validated['stack'], $actorId);
        } catch (RuntimeException $e) {
            return new JsonResponse([
                'message' => __('platform.infra_token_missing'),
            ], 503);
        }

        if (! $result['ok']) {
            // 409 pour les refus d'état (déjà active, non basculable), 422 pour
            // une cible inconnue : l'écran distingue « rien à faire » d'une
            // erreur de saisie.
            $status = in_array($result['reason'], ['stack_inconnue', 'tier_inconnu'], true) ? 422 : 409;

            return new JsonResponse([
                'message' => $result['message'],
                'reason' => $result['reason'],
                'tier' => $result['tier'],
                'stack' => $result['stack'],
                'active_stack' => $result['previous'],
            ], $status);
        }

        return new JsonResponse(['data' => $result], 202);
    }
}
