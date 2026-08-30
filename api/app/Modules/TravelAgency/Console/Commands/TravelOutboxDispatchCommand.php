<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelOutboxEvent;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxConsumerRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * TRAVEL-414 (#6066) — Consomme l'outbox des événements TravelAgency
 * (pattern CrmOutboxDispatchCommand #5741).
 *
 * Pour chaque événement pending et dû (available_at ≤ now), dans la limite
 * du lot :
 *   1. claim atomique pending → published (un seul worker traite) ;
 *   2. résolution du consommateur ; aucun → l'événement reste pending
 *      (l'adaptateur CRM/Accounting arrive avec les issues de
 *      consommation — ne pas dead-letter des événements métier) ;
 *   3. exécution idempotente dans le contexte tenant ;
 *   4. succès → published ; erreur transitoire → retry avec backoff
 *      exponentiel ; erreurs répétées (attempts ≥ max) → dead-letter (failed).
 *
 * Usage : php artisan travel:outbox-dispatch --limit=100
 */
class TravelOutboxDispatchCommand extends Command
{
    protected $signature = 'travel:outbox-dispatch
        {--limit=100 : nombre max d\'événements par passe (défaut 100)}';

    protected $description = 'Consomme les événements d\'outbox TravelAgency dus (idempotent, retry avec backoff, dead-letter).';

    /** Durée de lease d'un événement en cours de traitement (crash worker). */
    private const PROCESSING_LEASE_MINUTES = 15;

    public function __construct(
        private readonly TravelOutboxConsumerRegistry $registry,
        private readonly TenantManager $tenants,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $processed = 0;

        while ($processed < $limit) {
            $claimed = $this->claimBatch($limit - $processed);

            if ($claimed === []) {
                break;
            }

            foreach ($claimed as $eventId) {
                $this->processEvent((int) $eventId);
                $processed++;
            }
        }

        $this->info("[travel:outbox-dispatch] {$processed} événement(s) traité(s).");

        return self::SUCCESS;
    }

    /**
     * Claim atomique d'un lot : pending+due → published, ET reprise des
     * événements orphelins (lease expirée — worker crash).
     *
     * @return list<int>
     */
    private function claimBatch(int $limit): array
    {
        $ids = DB::table('travel_outbox_events')
            ->where(function ($query): void {
                $query->where('status', TravelOutboxEvent::STATUS_PENDING)
                    ->where('available_at', '<=', now())
                    ->orWhere(function ($query): void {
                        $query->where('status', TravelOutboxEvent::STATUS_PROCESSING)
                            ->where('updated_at', '<', now()->subMinutes(self::PROCESSING_LEASE_MINUTES));
                    });
            })
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->all();

        $claimed = [];
        foreach ($ids as $id) {
            $updated = DB::table('travel_outbox_events')
                ->where('id', $id)
                ->whereIn('status', [TravelOutboxEvent::STATUS_PENDING, TravelOutboxEvent::STATUS_PROCESSING])
                ->update(['status' => TravelOutboxEvent::STATUS_PROCESSING, 'updated_at' => now()]);

            if ($updated === 1) {
                $claimed[] = (int) $id;
            }
        }

        return $claimed;
    }

    private function processEvent(int $eventId): void
    {
        /** @var TravelOutboxEvent|null $event */
        $event = TravelOutboxEvent::query()->find($eventId);

        if (! $event instanceof TravelOutboxEvent) {
            return;
        }

        $consumer = $this->registry->consumerFor((string) $event->event_type);

        if ($consumer === null) {
            // Aucun adaptateur enregistré : on diffère (pas de dead-letter),
            // les consommateurs Notifications/CRM/Accounting arrivent avec les
            // issues de consommation. L'événement redevient pending.
            $event->update(['status' => TravelOutboxEvent::STATUS_PENDING]);

            $this->warn("[travel:outbox-dispatch] #{$event->id} pas de consommateur pour {$event->event_type} — différé.");

            return;
        }

        try {
            /** @var Company $company */
            $company = Company::query()->findOrFail((string) $event->company_id);

            $this->tenants->withinTenant($company, fn () => $consumer->handle(
                $event,
                is_array($event->payload_redacted) ? $event->payload_redacted : []
            ));

            $event->forceFill([
                'status' => TravelOutboxEvent::STATUS_PUBLISHED,
                'attempts' => $event->attempts + 1,
                'processed_at' => now(),
                'last_error' => null,
            ])->save();
        } catch (Throwable $e) {
            $this->retry($event, $e->getMessage());
        }
    }

    private function retry(TravelOutboxEvent $event, string $error): void
    {
        $attempts = $event->attempts + 1;

        if ($attempts >= TravelOutboxEvent::MAX_ATTEMPTS) {
            $this->deadLetter($event, $error);

            return;
        }

        // Backoff exponentiel + jitter borné : 10s, ~20s, ~40s, ~80s…
        $backoffSeconds = min(300, (10 * (2 ** ($attempts - 1))) + random_int(0, 5));

        $event->forceFill([
            'status' => TravelOutboxEvent::STATUS_PENDING,
            'attempts' => $attempts,
            'available_at' => now()->addSeconds($backoffSeconds),
            'last_error' => $error,
        ])->save();

        $this->warn("[travel:outbox-dispatch] #{$event->id} transitoire (tentative {$attempts}) : {$error}");
    }

    private function deadLetter(TravelOutboxEvent $event, string $error): void
    {
        $event->forceFill([
            'status' => TravelOutboxEvent::STATUS_FAILED,
            'attempts' => $event->attempts + 1,
            'last_error' => $error,
        ])->save();

        $this->error("[travel:outbox-dispatch] #{$event->id} dead-letter : {$error}");
    }
}
