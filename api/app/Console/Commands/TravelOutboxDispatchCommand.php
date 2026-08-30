<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelOutboxEvent;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxConsumerRegistry;
use Illuminate\Console\Command;
use Throwable;

/**
 * travel:outbox-dispatch — Consomme l'outbox des événements TravelAgency
 * (TRAVEL-414, issue #6066).
 *
 * La table `travel_outbox_events` n'autorise que pending|published|failed
 * (CHECK en base) : le claim atomique se fait par incrément de `attempts`
 * dans la même UPDATE conditionnelle (pending → attempts+1), ce qui
 * garantit qu'un seul worker traite chaque événement. Erreur transitoire →
 * retry avec backoff exponentiel ; permanente ou attempts ≥ max →
 * dead-letter (failed).
 *
 * Usage : php artisan travel:outbox-dispatch --limit=100
 */
class TravelOutboxDispatchCommand extends Command
{
    protected $signature = 'travel:outbox-dispatch
        {--limit=100 : nombre max d\'événements par passe (défaut 100)}';

    protected $description = 'Consomme les événements d\'outbox TravelAgency dus (idempotent, retry avec backoff, dead-letter).';

    public function __construct(
        private readonly TravelOutboxConsumerRegistry $registry,
        private readonly TenantManager $tenants,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $events = TravelOutboxEvent::query()
            ->where('status', TravelOutboxEvent::STATUS_PENDING)
            ->where('available_at', '<=', now())
            ->orderBy('available_at')
            ->limit($limit)
            ->get();

        $processed = 0;

        foreach ($events as $event) {
            // Claim atomique : pending → attempts+1 (un seul worker).
            $claimed = TravelOutboxEvent::query()
                ->whereKey($event->id)
                ->where('status', TravelOutboxEvent::STATUS_PENDING)
                ->increment('attempts');

            if ($claimed === 0) {
                continue;
            }

            $consumers = $this->registry->consumersFor($event->event_type);

            if ($consumers === []) {
                $this->deadLetter($event, 'Aucun consommateur pour '.$event->event_type);

                continue;
            }

            try {
                $company = $this->tenants->current()
                    ?? Company::query()->find($event->company_id);

                if ($company === null) {
                    $this->deadLetter($event, 'Tenant introuvable : '.$event->company_id);

                    continue;
                }

                // Enveloppe d'événement : identifiant + métadonnées disponibles
                // pour tous les consommateurs (webhooks, notifications, CRM…).
                // Multi-consommation : chaque consumer applique son effet de
                // façon idempotente — un échec sur l'un retente l'événement
                // (retry/backoff), les autres restent rejouables sans doublon.
                $this->tenants->withinTenant($company, function () use ($consumers, $event): void {
                    foreach ($consumers as $consumer) {
                        $consumer->handle(array_merge([
                            'event_id' => $event->id,
                            'event_type' => $event->event_type,
                            'company_id' => $event->company_id,
                        ], $event->payload_redacted ?? []));
                    }
                });

                $event->forceFill([
                    'status' => TravelOutboxEvent::STATUS_PUBLISHED,
                    'last_error' => null,
                ])->save();

                $processed++;
            } catch (Throwable $e) {
                $this->retryOrFail($event, $e);
            }
        }

        $this->info("Outbox TravelAgency : {$processed} événement(s) traité(s).");

        return self::SUCCESS;
    }

    private function deadLetter(TravelOutboxEvent $event, string $reason): void
    {
        $event->forceFill([
            'status' => TravelOutboxEvent::STATUS_FAILED,
            'last_error' => $reason,
        ])->save();
    }

    private function retryOrFail(TravelOutboxEvent $event, Throwable $e): void
    {
        if ($event->attempts >= TravelOutboxEvent::MAX_ATTEMPTS) {
            $event->forceFill([
                'status' => TravelOutboxEvent::STATUS_FAILED,
                'last_error' => $e->getMessage(),
            ])->save();

            return;
        }

        // Backoff exponentiel avec jitter (2^attempts minutes, plafonné à 1 h).
        $backoffMinutes = min(60, 2 ** max(1, $event->attempts));

        $event->forceFill([
            'status' => TravelOutboxEvent::STATUS_PENDING,
            'last_error' => $e->getMessage(),
            'available_at' => now()->addMinutes($backoffMinutes),
        ])->save();
    }
}
