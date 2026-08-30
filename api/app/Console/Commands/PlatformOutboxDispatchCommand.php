<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Platform\Domain\Exceptions\PermanentOutboxException;
use App\Modules\Platform\Domain\Models\PlatformOutboxEvent;
use App\Modules\Platform\Infrastructure\Services\PlatformOutboxConsumerRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * platform:outbox-dispatch — Consomme l'outbox des événements plateforme (MAT-008 #5866).
 *
 * Pour chaque événement pending et dû (available_at ≤ now), dans la limite
 * du lot :
 *   1. claim atomique pending → processing (un seul worker traite) ;
 *   2. résolution du consommateur ; aucun → dead-letter (permanent) ;
 *   3. exécution idempotente (dans le tenant de l'événement) ;
 *   4. succès → sent ; erreur transitoire → retry avec backoff exponentiel
 *      (+jitter) ; erreur permanente ou attempts ≥ max → dead-letter (failed).
 *
 * Usage : php artisan platform:outbox-dispatch --limit=100
 * Scheduler : toutes les minutes (ou worker dédié).
 */
class PlatformOutboxDispatchCommand extends Command
{
    protected $signature = 'platform:outbox-dispatch
        {--limit=100 : nombre max d\'événements par passe (défaut 100)}';

    protected $description = 'Consomme les événements d\'outbox plateforme dus (idempotent, retry avec backoff, dead-letter).';

    /** Durée de lease d'un événement en cours de traitement (crash worker). */
    private const PROCESSING_LEASE_MINUTES = 15;

    public function __construct(
        private readonly PlatformOutboxConsumerRegistry $registry,
        private readonly TenantManager $tenants,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $processed = 0;
        $sent = 0;
        $deadLettered = 0;

        while ($processed < $limit) {
            $claimed = $this->claimBatch($limit - $processed);

            if ($claimed === []) {
                break;
            }

            foreach ($claimed as $eventId) {
                $outcome = $this->processEvent((int) $eventId);
                $sent += $outcome === 'sent' ? 1 : 0;
                $deadLettered += $outcome === 'dead' ? 1 : 0;
                $processed++;
            }
        }

        $this->info("[platform:outbox-dispatch] {$processed} traité(s) — {$sent} envoyé(s), {$deadLettered} dead-letter.");

        return self::SUCCESS;
    }

    /**
     * Claim atomique d'un lot : pending+due → processing, ET reprise des
     * `processing` orphelins (lease expirée — worker crash).
     *
     * Un événement `processing` dont le lease a expiré est re-claimé par le
     * prochain worker : le crash d'un worker ne bloque plus la file. Les
     * tentatives ne sont PAS réinitialisées (une boucle crash-reclaim est
     * bornée par MAX_ATTEMPTS → dead-letter).
     *
     * @return list<int>
     */
    private function claimBatch(int $limit): array
    {
        $ids = DB::table('platform_outbox_events')
            ->where(function ($query): void {
                $query->where('status', PlatformOutboxEvent::STATUS_PENDING)
                    ->where('available_at', '<=', now())
                    ->orWhere(function ($query): void {
                        $query->where('status', PlatformOutboxEvent::STATUS_PROCESSING)
                            ->where('updated_at', '<', now()->subMinutes(self::PROCESSING_LEASE_MINUTES));
                    });
            })
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->all();

        $claimed = [];
        foreach ($ids as $id) {
            $updated = DB::table('platform_outbox_events')
                ->where('id', $id)
                ->whereIn('status', [PlatformOutboxEvent::STATUS_PENDING, PlatformOutboxEvent::STATUS_PROCESSING])
                ->update(['status' => PlatformOutboxEvent::STATUS_PROCESSING, 'updated_at' => now()]);

            if ($updated === 1) {
                $claimed[] = $id;
            }
        }

        return array_map('intval', $claimed);
    }

    private function processEvent(int $eventId): string
    {
        /** @var PlatformOutboxEvent|null $event */
        $event = PlatformOutboxEvent::query()->find($eventId);

        if (! $event instanceof PlatformOutboxEvent) {
            return 'skipped';
        }

        $consumer = $this->registry->consumerFor($event->event_type);

        if ($consumer === null) {
            $this->deadLetter($event, 'no_consumer');

            return 'dead';
        }

        try {
            /** @var Company $company */
            $company = Company::query()->findOrFail($event->company_id);

            // Le payload transmis au consommateur porte la référence de
            // l'événement outbox (traçabilité auditable) sans muter la
            // copie persistée.
            $payload = $event->payload;
            $payload['outbox_event_id'] = $event->id;

            $this->tenants->withinTenant($company, fn () => $consumer->handle($payload));

            $event->forceFill([
                'status' => PlatformOutboxEvent::STATUS_SENT,
                'attempts' => $event->attempts + 1,
                'processed_at' => now(),
                'last_error' => null,
            ])->save();

            return 'sent';
        } catch (PermanentOutboxException $e) {
            $this->deadLetter($event, 'permanent: '.$e->getMessage());

            return 'dead';
        } catch (Throwable $e) {
            // Transitoire par défaut : retry avec backoff (borné par MAX_ATTEMPTS).
            $this->retry($event, $e->getMessage());

            return 'retry';
        }
    }

    private function retry(PlatformOutboxEvent $event, string $error): void
    {
        $attempts = $event->attempts + 1;

        if ($attempts >= PlatformOutboxEvent::MAX_ATTEMPTS) {
            $this->deadLetter($event, $error);

            return;
        }

        // Backoff exponentiel + jitter borné : 10s, ~20s, ~40s, ~80s…
        $backoffSeconds = min(300, (10 * (2 ** ($attempts - 1))) + random_int(0, 5));

        $event->forceFill([
            'status' => PlatformOutboxEvent::STATUS_PENDING,
            'attempts' => $attempts,
            'available_at' => now()->addSeconds($backoffSeconds),
            'last_error' => $error,
        ])->save();

        $this->warn("[platform:outbox-dispatch] #{$event->id} transitoire (tentative {$attempts}) : {$error}");
    }

    private function deadLetter(PlatformOutboxEvent $event, string $error): void
    {
        $event->forceFill([
            'status' => PlatformOutboxEvent::STATUS_FAILED,
            'attempts' => $event->attempts + 1,
            'last_error' => $error,
        ])->save();

        $this->error("[platform:outbox-dispatch] #{$event->id} dead-letter : {$error}");
    }
}
