<?php

namespace App\Modules\Billing\Application\Actions;

use App\Modules\Billing\Infrastructure\Services\WebhookDispatcher;

class ProcessWebhook
{
    public function __construct(
        private readonly WebhookDispatcher $dispatcher,
    ) {}

    public function handle(string $companyId, string $event, array $payload): void
    {
        $this->dispatcher->dispatch($companyId, $event, $payload);
    }
}
