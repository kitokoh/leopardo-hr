<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Console\Commands;

use App\Modules\EduManager\Domain\Models\EduOutboxEvent;
use App\Modules\EduManager\Infrastructure\Services\EduOutboxConsumerRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * edu:outbox-dispatch — Consomme l'outbox des événements EduManager
 * (#5832, EDU-016 — pattern CrmOutboxDispatchCommand #5741).
 *
 * Pour chaque événement pending et dû (available_at ≤ now), dans la limite
 * du lot :
 *   1. claim atomique pending → processing (un seul worker traite) ;
 *   2. résolution du consommateur ; aucun → l'événement reste pending
 *      (l'adaptateur CRM/Accounting arrive avec les issues de
 *      consommation — ne pas dead-letter des événements métier) ;
 *   3. exécution idempotente dans le contexte tenant ;
 *   4. succès → sent ; erreur transitoire → retry avec backoff exponentiel
 *      ; erreurs répétées (attempts ≥ max) → dead-letter (failed).
 *
 * Usage : php artisan edu:outbox-dispatch --limit=100
 */
class EduOutboxDispatchCommand extends Command
{
    protected $signature = 'edu:outbox-dispatch
        {--limit=100 : nombre max d\'événements par passe (défaut 100)}';

    protected $description = 'Consomme les événements d\'outbox EduManager dus (idempotent, retry avec backoff, dead-letter).';

    /** Durée de lease d'un événement en cours de traitement (crash worker). */
    private const PROCESSING_LEASE_MINUTES = 15;

    public function __construct(private readonly EduOutboxConsumerRegistry $registry)
    {
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

        $this->info("[edu:outbox-dispatch] {$processed} événement(s) traité(s).");

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
        $ids = DB::table('edu_outbox_events')
            ->where(function ($query): void {
                $query->where('status', EduOutboxEvent::STATUS_PENDING)
                    ->where('available_at', '<=', now())
                    ->orWhere(function ($query): void {
                        $query->where('status', EduOutboxEvent::STATUS_PROCESSING)
                            ->where('updated_at', '<', now()->subMinutes(self::PROCESSING_LEASE_MINUTES));
                    });
            })
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->all();

        $claimed = [];
        foreach ($ids as $id) {
            $updated = DB::table('edu_outbox_events')
                ->where('id', $id)
                ->whereIn('status', [EduOutboxEvent::STATUS_PENDING, EduOutboxEvent::STATUS_PROCESSING])
                ->update(['status' => EduOutboxEvent::STATUS_PROCESSING, 'updated_at' => now()]);

            if ($updated === 1) {
                $claimed[] = (int) $id;
            }
        }

        return $claimed;
    }

    private function processEvent(int $eventId): void
    {
        /** @var EduOutboxEvent|null $event */
        $event = EduOutboxEvent::query()->find($eventId);

        if (! $event instanceof EduOutboxEvent) {
            return;
        }

        $consumer = $this->registry->consumerFor($event->event_type);

        if ($consumer === null) {
            // Aucun adaptateur enregistré : on diffère (pas de dead-letter),
            // les consommateurs CRM/Accounting arrivent avec les issues de
            // consommation. L'événement redevient pending sans incrémenter
            // les tentatives (available_at inchangé).
            $event->update(['status' => EduOutboxEvent::STATUS_PENDING]);

            $this->warn("[edu:outbox-dispatch] #{$event->id} pas de consommateur pour {$event->event_type} — différé.");

            return;
        }

        try {
            $consumer->handle($event, is_array($event->payload) ? $event->payload : []);

            $event->forceFill([
                'status' => EduOutboxEvent::STATUS_SENT,
                'attempts' => $event->attempts + 1,
                'processed_at' => now(),
                'last_error' => null,
            ])->save();
        } catch (Throwable $e) {
            $this->retry($event, $e->getMessage());
        }
    }

    private function retry(EduOutboxEvent $event, string $error): void
    {
        $attempts = $event->attempts + 1;

        if ($attempts >= EduOutboxEvent::MAX_ATTEMPTS) {
            $this->deadLetter($event, $error);

            return;
        }

        // Backoff exponentiel + jitter borné : 10s, ~20s, ~40s, ~80s…
        $backoffSeconds = min(300, (10 * (2 ** ($attempts - 1))) + random_int(0, 5));

        $event->forceFill([
            'status' => EduOutboxEvent::STATUS_PENDING,
            'attempts' => $attempts,
            'available_at' => now()->addSeconds($backoffSeconds),
            'last_error' => $error,
        ])->save();

        $this->warn("[edu:outbox-dispatch] #{$event->id} transitoire (tentative {$attempts}) : {$error}");
    }

    private function deadLetter(EduOutboxEvent $event, string $error): void
    {
        $event->forceFill([
            'status' => EduOutboxEvent::STATUS_FAILED,
            'attempts' => $event->attempts + 1,
            'last_error' => $error,
        ])->save();

        $this->error("[edu:outbox-dispatch] #{$event->id} dead-letter : {$error}");
    }
}
