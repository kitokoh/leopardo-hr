<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Actions;

use App\Modules\RestaurantManager\Domain\Contracts\RestaurantPrintJobRepositoryInterface;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPrintJob;

/**
 * REST-OS-5 (Mission Leopardo Restaurant OS) — Réclamation d'un travail
 * d'impression par le bridge local (REST-OS-3).
 *
 * Réclamation ATOMIQUE : le port n'affecte que `pending`, `failed`, ou
 * `claimed` dont le bail est expiré (bridge planté) — la BASE arbitre, jamais
 * le code appelant. Le premier gagne (affected = 1), les autres voient 0.
 * `attempts` est incrémenté à chaque tentative : un travail qui échoue reste
 * réessayable, jamais perdu.
 *
 * Le statut ne devient `printed` QUE sur accusé (voir AckPrintJobAction) : une
 * réclamation ne vaut jamais preuve d'impression.
 *
 * Garde de pureté #6568 : aucune facade Laravel ici — tout passe par le port.
 */
final class ClaimPrintJobAction
{
    public function __construct(
        private readonly RestaurantPrintJobRepositoryInterface $jobs,
    ) {}

    public function execute(RestaurantPrintJob $job, ?string $printerRef = null): ?RestaurantPrintJob
    {
        $configuredLease = config('restaurantmanager.printing.claim_lease_minutes', 15);
        $leaseMinutes = is_numeric($configuredLease) ? (int) $configuredLease : 15;

        $claimed = $this->jobs->claim(
            (int) $job->id,
            (string) $job->company_id,
            $printerRef,
            $leaseMinutes,
        );

        if ($claimed !== 1) {
            return null;
        }

        return $job->refresh();
    }
}
