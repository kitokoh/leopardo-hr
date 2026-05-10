<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\DispatchWebhook;
use App\Models\WebhookEndpoint;

class WebhookDispatcher
{
    public function dispatch(string $companyId, string $event, array $payload): void
    {
        $endpoints = WebhookEndpoint::where('company_id', $companyId)
            ->active()
            ->listeningTo($event)
            ->get();

        foreach ($endpoints as $endpoint) {
            DispatchWebhook::dispatch($endpoint, $event, $payload);
        }
    }
}
