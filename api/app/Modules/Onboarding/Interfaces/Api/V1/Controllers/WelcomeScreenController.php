<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Onboarding\Application\Actions\AcknowledgeWelcomeScreen;
use Illuminate\Http\JsonResponse;

/**
 * #7604 — acquittement de l'écran de bienvenue de première connexion
 * (tranche du critère 2 de #7490).
 *
 * `POST /api/v1/onboarding/welcome-ack` : marque l'écran de bienvenue comme vu
 * pour le tenant courant (`metadata.welcome_seen_at`) — persisté côté serveur,
 * jamais en `localStorage`, donc l'écran ne se réaffiche pas sur un autre
 * appareil.
 *
 * Réponse : `{ data: { welcome_seen_at, already_acknowledged } }`. L'état lu
 * par l'interface vient de `/auth/me` (`company.metadata`, #R8) — cet endpoint
 * ne fait qu'écrire, il n'y a rien à lire de plus.
 *
 * Garde RBAC : **responsable du tenant seulement** (`principal`/`rh`),
 * exactement la garde de l'activation de module
 * (`CompanyModuleController::activate`) — un employé n'acquitte pas l'écran de
 * bienvenue de son entreprise. Le middleware `tenant` scope l'écriture au
 * schéma du locataire courant.
 */
class WelcomeScreenController extends Controller
{
    public function __construct(
        private readonly AcknowledgeWelcomeScreen $acknowledgeWelcome,
    ) {}

    public function __invoke(): JsonResponse
    {
        /** @var Employee|null $actor */
        $actor = request()->user();

        // Un utilisateur authentifié est toujours un Employee (`auth:sanctum` +
        // middleware `tenant`) ; la garde est défensive (même pattern que
        // `OnboardingChecklistController`).
        abort_if(! $actor instanceof Employee, 401);
        abort_unless($actor->hasManagerRole('principal', 'rh'), 403);

        $result = $this->acknowledgeWelcome->execute(currentCompany());

        return response()->json([
            'data' => [
                'welcome_seen_at' => $result['seen_at'],
                'already_acknowledged' => $result['already_acknowledged'],
            ],
        ]);
    }
}
