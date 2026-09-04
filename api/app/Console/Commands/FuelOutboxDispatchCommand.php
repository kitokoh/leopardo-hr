<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\FuelStation\Domain\Exceptions\PermanentFuelOutboxException;
use App\Modules\FuelStation\Domain\Models\FuelOutboxEvent;
use App\Modules\FuelStation\Infrastructure\Services\FuelOutboxConsumerRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * fuel:outbox-dispatch — Consomme l'outbox des événements FuelStation
 * (FUEL-015/019).
 *
 * Même protocole que crm:outbox-dispatch (#5741, BC-14) : claim atomique
 * pending → processing, lease 15 min (reprise après crash worker), retry
 * avec backoff exponentiel borné par MAX_ATTEMPTS, dead-letter en erreur
 * permanente. Les consommateurs sont exécutés dans le contexte tenant de
 * l'événement (TenantManager::withinTenant).
 *
 * Usage : php artisan fuel:outbox-dispatch --limit=100
 * Scheduler : toutes les minutes (routes/console.php).
 */
class FuelOutboxDispatchCommand extends Command
{
    protected $signature = 'fuel:outbox-dispatch
        {--limit=100 : nombre max d\'événements par passe (défaut 100)}';

    protected $description = 'Consomme les événements d\'outbox FuelStation dus (idempotent, retry avec backoff, dead-letter).';

    /** Durée de lease d'un événement en cours de traitement (BC-14). */
    private const PROCESSING_LEASE_MINUTES = 15;

    public function __construct(
        private readonly FuelOutboxConsumerRegistry $registry,
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

        $this->info("[fuel:outbox-dispatch] {$processed} événement(s) traité(s).");

        return self::SUCCESS;
    }

    /**
     * Claim atomique d'un lot : pending+due → processing, ET reprise des
     * `processing` orphelins (lease expirée — worker crash, BC-14).
     *
     * @return list<int>
     */
    private function claimBatch(int $limit): array
    {
        $ids = DB::table('fuel_outbox_events')
            ->where(function ($query): void {
                $query->where('status', FuelOutboxEvent::STATUS_PENDING)
                    ->where('available_at', '<=', now())
                    ->orWhere(function ($query): void {
                        $query->where('status', FuelOutboxEvent::STATUS_PROCESSING)
                            ->where('updated_at', '<', now()->subMinutes(self::PROCESSING_LEASE_MINUTES));
                    });
            })
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->all();

        $claimed = [];
        foreach ($ids as $id) {
            $updated = DB::table('fuel_outbox_events')
                ->where('id', $id)
                ->whereIn('status', [FuelOutboxEvent::STATUS_PENDING, FuelOutboxEvent::STATUS_PROCESSING])
                ->update(['status' => FuelOutboxEvent::STATUS_PROCESSING, 'updated_at' => now()]);

            if ($updated === 1) {
                $claimed[] = $id;
            }
        }

        return array_map('intval', $claimed);
    }

    private function processEvent(int $eventId): void
    {
        /** @var FuelOutboxEvent|null $event */
        $event = FuelOutboxEvent::query()->find($eventId);

        if (! $event instanceof FuelOutboxEvent) {
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

            $this->tenants->withinTenant($company, fn () => $consumer->handle($event->payload));

            $event->forceFill([
                'status' => FuelOutboxEvent::STATUS_SENT,
                'attempts' => $event->attempts + 1,
                'processed_at' => now(),
                'last_error' => null,
            ])->save();
        } catch (PermanentFuelOutboxException $e) {
            $this->deadLetter($event, 'permanent: '.$e->getMessage());
        } catch (Throwable $e) {
            // Transitoire par défaut : retry avec backoff (borné par MAX_ATTEMPTS).
            $this->retry($event, $e->getMessage());
        }
    }

    private function retry(FuelOutboxEvent $event, string $error): void
    {
        $attempts = $event->attempts + 1;

        if ($attempts >= FuelOutboxEvent::MAX_ATTEMPTS) {
            $this->deadLetter($event, $error);

            return;
        }

        // Backoff exponentiel + jitter borné : 10s, ~20s, ~40s, ~80s…
        $backoffSeconds = min(300, (10 * (2 ** ($attempts - 1))) + random_int(0, 5));

        $event->forceFill([
            'status' => FuelOutboxEvent::STATUS_PENDING,
            'attempts' => $attempts,
            'available_at' => now()->addSeconds($backoffSeconds),
            'last_error' => $error,
        ])->save();

        $this->warn("[fuel:outbox-dispatch] #{$event->id} transitoire (tentative {$attempts}) : {$error}");
    }

    private function deadLetter(FuelOutboxEvent $event, string $error): void
    {
        $event->forceFill([
            'status' => FuelOutboxEvent::STATUS_FAILED,
            'attempts' => $event->attempts + 1,
            'last_error' => $error,
        ])->save();

        $this->error("[fuel:outbox-dispatch] #{$event->id} dead-letter : {$error}");
    }
}
