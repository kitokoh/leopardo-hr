<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Models\TravelWebhookSubscription;

/**
 * TRAVEL-806 (#6097) — Policy des abonnements webhooks transporteurs.
 *
 * Réservé à `travel.manage` (principal/rh/manager) : un abonnement expose
 * une URL de réception et un secret de signature — fail-closed.
 */
class TravelWebhookSubscriptionPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager');
    }

    public function view(Employee $actor, TravelWebhookSubscription $subscription): bool
    {
        return $this->viewAny($actor) && $subscription->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function update(Employee $actor, TravelWebhookSubscription $subscription): bool
    {
        return $this->view($actor, $subscription);
    }

    public function delete(Employee $actor, TravelWebhookSubscription $subscription): bool
    {
        return $this->view($actor, $subscription);
    }
}
