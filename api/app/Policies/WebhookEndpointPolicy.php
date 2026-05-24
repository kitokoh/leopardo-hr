<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Employee;
use App\Models\WebhookEndpoint;

class WebhookEndpointPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal');
    }

    public function view(Employee $actor, WebhookEndpoint $webhook): bool
    {
        return $webhook->company_id === $actor->company_id
            && $actor->hasManagerRole('principal');
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal');
    }

    public function update(Employee $actor, WebhookEndpoint $webhook): bool
    {
        return $webhook->company_id === $actor->company_id
            && $actor->hasManagerRole('principal');
    }

    public function delete(Employee $actor, WebhookEndpoint $webhook): bool
    {
        return $webhook->company_id === $actor->company_id
            && $actor->hasManagerRole('principal');
    }
}
