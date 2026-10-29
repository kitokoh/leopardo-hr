<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Models\TravelWebhookSubscription;

/**
 * TRAVEL-806 (#6097) — Abonnements webhook : lecture pour les rôles
 * opérationnels du tenant, écriture réservée aux rôles gestion.
 */
class TravelWebhookSubscriptionPolicy
{
    /**
     * Lecture réservée aux rôles gestion.
     *
     * #8396 — `viewAny()` rendait `true` à TOUT employé authentifié : la surface
     * d'abonnements webhook (URLs de livraison, préfixes de secret, événements
     * souscrits) était donc lisible par n'importe quel salarié, alors que la
     * politique annoncée et le contrat testé la réservent aux rôles gestion.
     * Une liste d'endpoints sortants est une information d'infrastructure : la
     * laisser ouverte à tous est une fuite de reconnaissance, pas un confort.
     */
    public function viewAny(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }

    public function view(Employee $actor, TravelWebhookSubscription $subscription): bool
    {
        return $subscription->company_id === $actor->company_id
            && $actor->hasManagerRole('principal', 'rh');
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }

    public function update(Employee $actor, TravelWebhookSubscription $subscription): bool
    {
        return $subscription->company_id === $actor->company_id
            && $actor->hasManagerRole('principal', 'rh');
    }

    public function delete(Employee $actor, TravelWebhookSubscription $subscription): bool
    {
        return $subscription->company_id === $actor->company_id
            && $actor->hasManagerRole('principal', 'rh');
    }
}
