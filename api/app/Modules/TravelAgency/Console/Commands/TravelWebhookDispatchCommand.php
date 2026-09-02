<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelOutboxEvent;
use App\Modules\TravelAgency\Domain\Models\TravelWebhookDelivery;
use App\Modules\TravelAgency\Domain\Models\TravelWebhookSubscription;
use App\Modules\TravelAgency\Infrastructure\Services\TravelWebhookDispatcher;
use Illuminate\Console\Command;

/**
 * TRAVEL-806 (#6097) — Dispatch des webhooks transporteurs.
 *
 * 1. Matérialise les livraisons dues depuis les événements d'outbox
 *    (idempotent : une livraison par (abonnement, événement)).
 * 2. Envoie les livraisons dues (pending / failed en backoff), retries avec
 *    backoff exponentiel, dead-letter après 5 tentatives.
 */
class TravelWebhookDispatchCommand extends Command
{
    protected $signature = 'travel:webhook-dispatch
        {--limit=50 : nombre max de livraisons par passe (défaut 50)}';

    protected $description = 'Matérialise et envoie les livraisons webhook transporteurs dues (idempotent, retry/backoff, dead-letter).';

    public function __construct(
        private readonly TravelWebhookDispatcher $dispatcher,
        private readonly TenantManager $tenants,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $materialized = 0;
        $sent = 0;

        foreach (Company::query()->cursor() as $company) {
            $materialized += $this->tenants->withinTenant($company, function () use ($limit): int {
                return $this->materializeDeliveries($limit);
            });
        }

        foreach (Company::query()->cursor() as $company) {
            $sent += $this->tenants->withinTenant($company, function () use ($limit): int {
                return $this->dispatchDue($limit);
            });
        }

        $this->info("travel:webhook-dispatch — livraisons matérialisées : {$materialized}, envoyées : {$sent}.");

        return self::SUCCESS;
    }

    /** Crée les livraisons manquantes à partir des événements d'outbox. */
    private function materializeDeliveries(int $limit): int
    {
        $subscriptions = TravelWebhookSubscription::query()
            ->where('active', true)
            ->get();

        if ($subscriptions->isEmpty()) {
            return 0;
        }

        $eventTypes = $subscriptions
            ->flatMap(fn (TravelWebhookSubscription $s) => $s->events ?? [])
            ->unique()
            ->all();

        $events = TravelOutboxEvent::query()
            ->whereIn('event_type', $eventTypes)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $count = 0;
        foreach ($events as $event) {
            foreach ($subscriptions as $subscription) {
                if (! $subscription->supports($event->event_type)) {
                    continue;
                }

                $created = TravelWebhookDelivery::query()->firstOrCreate(
                    ['subscription_id' => $subscription->id, 'outbox_event_id' => $event->id],
                    [
                        'company_id' => $subscription->company_id,
                        'event_type' => $event->event_type,
                        'payload_redacted' => $event->payload_redacted ?? [],
                        'status' => 'pending',
                        'attempts' => 0,
                    ],
                );

                if ($created->wasRecentlyCreated) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /** Envoie les livraisons dues (pending ou failed en backoff). */
    private function dispatchDue(int $limit): int
    {
        $due = TravelWebhookDelivery::query()
            ->whereIn('status', ['pending', 'failed'])
            ->where(fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        $sent = 0;
        foreach ($due as $delivery) {
            if ($this->dispatcher->deliver($delivery)) {
                $sent++;
            }
        }

        return $sent;
    }
}
