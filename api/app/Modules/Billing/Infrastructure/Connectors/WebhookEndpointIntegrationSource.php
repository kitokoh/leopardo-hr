<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Connectors;

use App\Modules\Billing\Domain\Models\WebhookEndpoint;
use App\Shared\Contracts\Connectors\ConnectorIntegrationSourceInterface;

/** #9846 — webhooks sortants du tenant. */
final class WebhookEndpointIntegrationSource implements ConnectorIntegrationSourceInterface
{
    public function key(): string
    {
        return 'webhooks';
    }

    public function label(): string
    {
        return 'Webhooks sortants';
    }

    public function category(): string
    {
        return 'other';
    }

    public function href(): string
    {
        return '/settings/webhooks';
    }

    public function detail(): string
    {
        return 'Notifications envoyées vers vos systèmes quand un événement se produit.';
    }

    /** @return list<'platform'|'tenant'> */
    public function audiences(): array
    {
        return ['tenant'];
    }

    public function count(): int
    {
        return WebhookEndpoint::query()->count();
    }
}
