<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Paiements\Domain\Models\PaymentWebhookEndpoint;

/**
 * BC-38 PAIEMENTS (#8711) — autorisation explicite sur un endpoint de webhook.
 *
 * Un endpoint porte un **secret de signature** : c'est la surface la plus
 * sensible du BC. Elle reste entierement plateforme — aucun role tenant ne la
 * lit, ne la cree, ni ne la modifie. La policy existe pour que cette regle soit
 * verifiee par test plutot que par l'absence de controleur.
 */
class PaymentWebhookEndpointPolicy
{
    public function view(Employee $actor, PaymentWebhookEndpoint $endpoint): bool
    {
        return false;
    }

    public function create(Employee $actor): bool
    {
        return false;
    }

    public function update(Employee $actor, PaymentWebhookEndpoint $endpoint): bool
    {
        return false;
    }
}
