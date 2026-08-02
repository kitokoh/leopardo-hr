<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Actions;

use App\Modules\Billing\Infrastructure\Services\WebhookDispatcher;

class ProcessWebhook
{
    public function __construct(
        private readonly WebhookDispatcher $dispatcher,
    ) {}

    public function handle(string $provider, string $payload, array $headers = []): void
    {
        $this->dispatcher->dispatch($provider, $payload, $headers);
    }
}
