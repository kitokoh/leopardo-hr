<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Repositories;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * #8448 — journal d'idempotence des gestes cuisine hors ligne.
 *
 * L'accès à la table vit en Infrastructure : `check-layer-purity.sh` interdit
 * les façades Laravel depuis `Application/`, et la contrainte est saine — un
 * cas d'usage ne doit pas décider comment on écrit en base.
 *
 * La garantie d'idempotence est portée par la **contrainte d'unicité**
 * (`company_id` + `client_action_id`), pas par un `SELECT` préalable : deux
 * rejeux concurrents liraient tous les deux « absent » avant que l'un des deux
 * n'écrive. Ici le second `INSERT` échoue au niveau du moteur.
 */
final class RestaurantKitchenActionRepository
{
    public function alreadyApplied(string $companyId, string $clientActionId): bool
    {
        return DB::table('restaurant_kitchen_actions')
            ->where('company_id', $companyId)
            ->where('client_action_id', $clientActionId)
            ->exists();
    }

    /**
     * Enregistre le geste. Retourne `false` si un rejeu concurrent l'a déjà
     * enregistré — ce n'est pas une erreur, c'est l'idempotence qui fonctionne.
     */
    public function record(
        string $companyId,
        string $clientActionId,
        int $orderId,
        string $action,
        string $resultingStatus,
    ): bool {
        try {
            DB::table('restaurant_kitchen_actions')->insert([
                'company_id' => $companyId,
                'client_action_id' => $clientActionId,
                'restaurant_order_id' => $orderId,
                'action' => $action,
                'resulting_status' => $resultingStatus,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }
}
