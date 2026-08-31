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
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, TravelWebhookSubscription $subscription): bool
    {
        return $subscription->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager');
    }

    public function update(Employee $actor, TravelWebhookSubscription $subscription): bool
    {
        return $subscription->company_id === $actor->company_id
            && $actor->hasManagerRole('principal', 'rh', 'manager');
    }

    public function delete(Employee $actor, TravelWebhookSubscription $subscription): bool
    {
        return $subscription->company_id === $actor->company_id
            && $actor->hasManagerRole('principal', 'rh', 'manager');
    }
}
