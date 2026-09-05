<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOutboxEvent;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantOutboxConsumerRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * restaurant:outbox-dispatch — Consomme l'outbox des événements
 * RestaurantManager (RESTO-806/#6227, miroir crm:outbox-dispatch #5741).
 *
 * Pour chaque événement pending et dû (available_at ≤ now), dans la limite
 * du lot : claim atomique pending → processing, résolution du consommateur
 * (aucun → dead-letter), exécution idempotente, succès → published ;
 * erreur transitoire → retry avec backoff exponentiel (+jitter) ; erreur
 * permanente ou attempts ≥ MAX_ATTEMPTS → dead-letter (failed).
 *
 * Usage : php artisan restaurant:outbox-dispatch --limit=100
 * Scheduler : toutes les minutes (voir RUNBOOK_PILOT_RESTAURANTMANAGER).
 */
class RestaurantOutboxDispatchCommand extends Command
{
    protected $signature = 'restaurant:outbox-dispatch
        {--limit=100 : max events per pass (default 100)}';

    protected $description = 'Consumes due RestaurantManager outbox events (idempotent, retry with backoff, dead-letter).';

    /** Durée de lease d'un événement en cours de traitement. */
    private const PROCESSING_LEASE_MINUTES = 15;

    public function __construct(
        private readonly RestaurantOutboxConsumerRegistry $registry,
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

        $this->info("[restaurant:outbox-dispatch] {$processed} event(s) processed.");

        return self::SUCCESS;
    }

    /**
     * Claim atomique d'un lot : pending+due → processing, ET reprise des
     * `processing` orphelins (lease expirée — worker crash).
     *
     * @return list<int>
     */
    private function claimBatch(int $limit): array
    {
        $ids = DB::table('restaurant_outbox_events')
            ->where(function ($query): void {
                $query->where('status', RestaurantOutboxEvent::STATUS_PENDING)
                    ->where('available_at', '<=', now())
                    ->orWhere(function ($query): void {
                        $query->where('status', RestaurantOutboxEvent::STATUS_PROCESSING)
                            ->where('updated_at', '<', now()->subMinutes(self::PROCESSING_LEASE_MINUTES));
                    });
            })
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->all();

        $claimed = [];
        foreach ($ids as $id) {
            $updated = DB::table('restaurant_outbox_events')
                ->where('id', $id)
                ->whereIn('status', [RestaurantOutboxEvent::STATUS_PENDING, RestaurantOutboxEvent::STATUS_PROCESSING])
                ->update(['status' => RestaurantOutboxEvent::STATUS_PROCESSING, 'updated_at' => now()]);

            if ($updated === 1) {
                $claimed[] = $id;
            }
        }

        return array_map('intval', $claimed);
    }

    private function processEvent(int $eventId): void
    {
        /** @var RestaurantOutboxEvent|null $event */
        $event = RestaurantOutboxEvent::query()->find($eventId);

        if (! $event instanceof RestaurantOutboxEvent) {
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
                'status' => RestaurantOutboxEvent::STATUS_PUBLISHED,
                'attempts' => $event->attempts + 1,
                'available_at' => null,
                'last_error' => null,
            ])->save();
        } catch (Throwable $e) {
            // Transitoire par défaut : retry avec backoff (borné par MAX_ATTEMPTS).
            $this->retry($event, $e->getMessage());
        }
    }

    private function retry(RestaurantOutboxEvent $event, string $error): void
    {
        $attempts = $event->attempts + 1;

        if ($attempts >= RestaurantOutboxEvent::MAX_ATTEMPTS) {
            $this->deadLetter($event, $error);

            return;
        }

        // Backoff exponentiel + jitter borné : 10s, ~20s, ~40s, ~80s…
        $backoffSeconds = min(300, (10 * (2 ** ($attempts - 1))) + random_int(0, 5));

        $event->forceFill([
            'status' => RestaurantOutboxEvent::STATUS_PENDING,
            'attempts' => $attempts,
            'available_at' => now()->addSeconds($backoffSeconds),
            'last_error' => $error,
        ])->save();

        $this->warn("[restaurant:outbox-dispatch] #{$event->id} transitoire (tentative {$attempts}) : {$error}");
    }

    private function deadLetter(RestaurantOutboxEvent $event, string $error): void
    {
        $event->forceFill([
            'status' => RestaurantOutboxEvent::STATUS_FAILED,
            'attempts' => $event->attempts + 1,
            'last_error' => $error,
        ])->save();

        $this->error("[restaurant:outbox-dispatch] #{$event->id} dead-letter : {$error}");
    }
}
