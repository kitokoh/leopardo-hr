<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Services;

use App\Jobs\DispatchWebhook;
use App\Modules\Billing\Domain\Models\WebhookEndpoint;
use Illuminate\Support\Str;

class WebhookDispatcher
{
    /**
     * Planifie une livraison webhook pour tous les endpoints actifs du tenant
     * qui écoutent l'événement.
     *
     * Issue #5744 : le `correlation_id` (traçage de bout en bout de
     * l'occurrence métier) et le `occurred_at` (moment métier) sont générés
     * UNE fois par dispatch et partagés entre tous les endpoints du tenant —
     * chaque endpoint reçoit en revanche son propre `Webhook-Id` de livraison.
     */
    public function dispatch(string $companyId, string $event, array $payload): void
    {
        $correlationId = (string) Str::uuid();
        $occurredAt = now()->toIso8601String();

        $endpoints = WebhookEndpoint::where('company_id', $companyId)
            ->active()
            ->listeningTo($event)
            ->get();

        foreach ($endpoints as $endpoint) {
            DispatchWebhook::dispatch(
                $endpoint,
                $event,
                $payload,
                WebhookEnvelopeBuilder::CURRENT_VERSION,
                $correlationId,
                $occurredAt,
            );
        }
    }
}
