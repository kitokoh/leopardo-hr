<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\CRM\Domain\Exceptions\PermanentOutboxException;
use App\Modules\CRM\Domain\Models\CrmOutboxEvent;
use App\Modules\CRM\Infrastructure\Services\CrmOutboxConsumerRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * crm:outbox-dispatch — Consomme l'outbox des événements CRM (#5741).
 *
 * Pour chaque événement pending et dû (available_at ≤ now), dans la limite
 * du lot :
 *   1. claim atomique pending → processing (un seul worker traite) ;
 *   2. résolution du consommateur ; aucun → dead-letter (permanent) ;
 *   3. exécution idempotente ;
 *   4. succès → sent ; erreur transitoire → retry avec backoff exponentiel
 *      (+jitter) ; erreur permanente ou attempts ≥ max → dead-letter (failed).
 *
 * Usage : php artisan crm:outbox-dispatch --limit=100
 * Scheduler : toutes les minutes (ou worker dédié).
 */
class CrmOutboxDispatchCommand extends Command
{
    protected $signature = 'crm:outbox-dispatch
        {--limit=100 : nombre max d\'événements par passe (défaut 100)}';

    protected $description = 'Consomme les événements d\'outbox CRM dus (idempotent, retry avec backoff, dead-letter).';

    public function __construct(
        private readonly CrmOutboxConsumerRegistry $registry,
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

        $this->info("[crm:outbox-dispatch] {$processed} événement(s) traité(s).");

        return self::SUCCESS;
    }

    /**
     * Claim atomique d'un lot : pending+due → processing.
     *
     * @return list<int>
     */
    private function claimBatch(int $limit): array
    {
        $ids = DB::table('crm_outbox_events')
            ->where('status', CrmOutboxEvent::STATUS_PENDING)
            ->where('available_at', '<=', now())
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->all();

        $claimed = [];
        foreach ($ids as $id) {
            $updated = DB::table('crm_outbox_events')
                ->where('id', $id)
                ->where('status', CrmOutboxEvent::STATUS_PENDING)
                ->update(['status' => CrmOutboxEvent::STATUS_PROCESSING, 'updated_at' => now()]);

            if ($updated === 1) {
                $claimed[] = $id;
            }
        }

        return array_map('intval', $claimed);
    }

    private function processEvent(int $eventId): void
    {
        /** @var CrmOutboxEvent|null $event */
        $event = CrmOutboxEvent::query()->find($eventId);

        if (! $event instanceof CrmOutboxEvent) {
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
                'status' => CrmOutboxEvent::STATUS_SENT,
                'attempts' => $event->attempts + 1,
                'processed_at' => now(),
                'last_error' => null,
            ])->save();
        } catch (PermanentOutboxException $e) {
            $this->deadLetter($event, 'permanent: '.$e->getMessage());
        } catch (Throwable $e) {
            // Transitoire par défaut : retry avec backoff (borné par MAX_ATTEMPTS).
            $this->retry($event, $e->getMessage());
        }
    }

    private function retry(CrmOutboxEvent $event, string $error): void
    {
        $attempts = $event->attempts + 1;

        if ($attempts >= CrmOutboxEvent::MAX_ATTEMPTS) {
            $this->deadLetter($event, $error);

            return;
        }

        // Backoff exponentiel + jitter borné : 10s, ~20s, ~40s, ~80s…
        $backoffSeconds = min(300, (10 * (2 ** ($attempts - 1))) + random_int(0, 5));

        $event->forceFill([
            'status' => CrmOutboxEvent::STATUS_PENDING,
            'attempts' => $attempts,
            'available_at' => now()->addSeconds($backoffSeconds),
            'last_error' => $error,
        ])->save();

        $this->warn("[crm:outbox-dispatch] #{$event->id} transitoire (tentative {$attempts}) : {$error}");
    }

    private function deadLetter(CrmOutboxEvent $event, string $error): void
    {
        $event->forceFill([
            'status' => CrmOutboxEvent::STATUS_FAILED,
            'attempts' => $event->attempts + 1,
            'last_error' => $error,
        ])->save();

        $this->error("[crm:outbox-dispatch] #{$event->id} dead-letter : {$error}");
    }
}
