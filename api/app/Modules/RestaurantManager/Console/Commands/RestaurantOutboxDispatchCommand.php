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
 * RestaurantManager (RESTO-606/#6211, pattern crm:outbox-dispatch #5741).
 *
 * L'outbox est une table TENANT (schéma PostgreSQL par company) : la commande
 * itère toutes les companies et traite les événements de chaque tenant dans
 * son contexte (`withinTenant`), dans la limite du lot par passe.
 *
 * Pour chaque événement pending et dû (available_at ≤ now) :
 *   1. claim atomique pending → published (un seul worker traite) ;
 *   2. résolution du consommateur ; aucun → dead-letter (permanent) ;
 *   3. exécution idempotente dans le contexte tenant ;
 *   4. succès → published (attempts +1) ; erreur transitoire → retry avec
 *      backoff exponentiel (+jitter) ; attempts ≥ max → dead-letter.
 *
 * Usage : php artisan restaurant:outbox-dispatch --limit=100
 * Scheduler : toutes les minutes.
 */
class RestaurantOutboxDispatchCommand extends Command
{
    protected $signature = 'restaurant:outbox-dispatch
        {--limit=100 : nombre max d\'événements par passe (défaut 100)}';

    protected $description = 'Consomme les événements d\'outbox RestaurantManager dus (idempotent, retry avec backoff, dead-letter).';

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

        $companies = Company::query()
            ->orderBy('id')
            ->get();

        foreach ($companies as $company) {
            if ($processed >= $limit) {
                break;
            }

            $processed += (int) $this->tenants->withinTenant(
                $company,
                fn (): int => $this->processTenant($limit - $processed),
            );
        }

        $this->info("[restaurant:outbox-dispatch] {$processed} événement(s) traité(s).");

        return self::SUCCESS;
    }

    private function processTenant(int $limit): int
    {
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

        return $processed;
    }

    /**
     * Claim atomique d'un lot : pending+due → published.
     *
     * @return list<int>
     */
    private function claimBatch(int $limit): array
    {
        $ids = DB::table('restaurant_outbox_events')
            ->where('status', RestaurantOutboxEvent::STATUS_PENDING)
            ->where('available_at', '<=', now())
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->all();

        $claimed = [];
        foreach ($ids as $id) {
            $updated = DB::table('restaurant_outbox_events')
                ->where('id', $id)
                ->where('status', RestaurantOutboxEvent::STATUS_PENDING)
                ->update(['status' => RestaurantOutboxEvent::STATUS_PUBLISHED, 'updated_at' => now()]);

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
            $consumer->handle($event->payload_redacted);

            $event->forceFill([
                'status' => RestaurantOutboxEvent::STATUS_PUBLISHED,
                'attempts' => $event->attempts + 1,
                'available_at' => now(),
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
