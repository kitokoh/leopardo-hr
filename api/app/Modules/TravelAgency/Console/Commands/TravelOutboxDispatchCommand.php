<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Exceptions\PermanentOutboxException;
use App\Modules\TravelAgency\Domain\Exceptions\TransientOutboxException;
use App\Modules\TravelAgency\Domain\Models\TravelOutboxEvent;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxConsumerRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * travel:outbox-dispatch — Consomme l'outbox des événements TravelAgency
 * (#6066, TRAVEL-414).
 *
 * Miroir du pattern `crm:outbox-dispatch` (#5741). Pour chaque événement
 * pending et dû (available_at ≤ now), dans la limite du lot :
 *   1. claim atomique pending → processing (un seul worker traite, lease
 *      `PROCESSING_LEASE_MINUTES`, reprise des orphelins après crash) ;
 *   2. résolution du consommateur ; aucun → dead-letter (permanent) ;
 *   3. exécution idempotente dans le contexte tenant de l'événement ;
 *   4. succès → published + processed_at ; erreur transitoire → retry avec
 *      backoff exponentiel (+jitter) ; erreur permanente ou attempts ≥ max
 *      → dead-letter (failed).
 *
 * Usage : php artisan travel:outbox-dispatch --limit=100
 * Scheduler : toutes les minutes (routes/console.php).
 */
class TravelOutboxDispatchCommand extends Command
{
    protected $signature = 'travel:outbox-dispatch
        {--limit=100 : nombre max d\'événements par passe (défaut 100)}';

    protected $description = 'Consomme les événements d\'outbox TravelAgency dus (idempotent, retry avec backoff, dead-letter).';

    /** Durée de lease d'un événement en cours de traitement (pattern #5741). */
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
     * Claim atomique d'un lot : pending+due → processing, ET reprise des
     * `processing` orphelins (lease expirée — worker crash, pattern #5741).
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
                $claimed[] = $id;
            }
        }

        return array_map('intval', $claimed);
    }

    private function processEvent(int $eventId): void
    {
        /** @var TravelOutboxEvent|null $event */
        $event = TravelOutboxEvent::query()->find($eventId);

        if (! $event instanceof TravelOutboxEvent) {
            return;
        }

        $consumer = $this->registry->consumerFor($event->event_type);

        if ($consumer === null) {
            $this->deadLetter($event, 'no_consumer');

            return;
        }

        try {
            /** @var Company $company */
            $company = Company::query()->findOrFail($event->company_id);

            $this->tenants->withinTenant($company, fn () => $consumer->handle($event->payload_redacted));

            $event->forceFill([
                'status' => TravelOutboxEvent::STATUS_PUBLISHED,
                'attempts' => $event->attempts + 1,
                'processed_at' => now(),
                'last_error' => null,
            ])->save();
        } catch (PermanentOutboxException $e) {
            $this->deadLetter($event, 'permanent: '.$e->getMessage());
        } catch (TransientOutboxException $e) {
            $this->retry($event, $e->getMessage());
        } catch (Throwable $e) {
            // Transitoire par défaut : retry avec backoff (borné par MAX_ATTEMPTS).
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
