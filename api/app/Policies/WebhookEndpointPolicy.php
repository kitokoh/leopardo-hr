<?php

declare(strict_types=1);

namespace App\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Billing\Domain\Models\WebhookEndpoint;

/**
 * #3949 : la gestion des webhooks est réservée aux managers `principal` —
 * garde unifiée au niveau Policy (au lieu d'éclater contrôleur + FormRequest).
 * La règle métier vit ici ; contrôleur et FormRequests la délèguent.
 */
class WebhookEndpointPolicy
{
    public function manage(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal');
    }

    public function viewAny(Employee $actor): bool
    {
        return $this->manage($actor);
    }

    public function create(Employee $actor): bool
    {
        return $this->manage($actor);
    }

    public function view(Employee $actor, WebhookEndpoint $webhookEndpoint): bool
    {
        return $this->manage($actor) && $webhookEndpoint->company_id === $actor->company_id;
    }

    public function update(Employee $actor, WebhookEndpoint $webhookEndpoint): bool
    {
        return $this->view($actor, $webhookEndpoint);
    }

    public function delete(Employee $actor, WebhookEndpoint $webhookEndpoint): bool
    {
        return $this->view($actor, $webhookEndpoint);
    }

    public function test(Employee $actor, WebhookEndpoint $webhookEndpoint): bool
    {
        return $this->view($actor, $webhookEndpoint);
    }
}
