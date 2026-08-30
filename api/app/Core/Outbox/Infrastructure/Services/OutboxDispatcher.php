<?php

declare(strict_types=1);

namespace App\Core\Outbox\Infrastructure\Services;

use App\Core\Outbox\Domain\Contracts\OutboxConsumer;
use App\Core\Outbox\Domain\Contracts\TenantScopedOutboxConsumer;
use App\Core\Outbox\Domain\Exceptions\PermanentOutboxException;
use App\Core\Outbox\Domain\Exceptions\TransientOutboxException;
use App\Core\Outbox\Domain\Models\OutboxEvent;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * MAT-008 (#5866) — Dispatcher de l'outbox générique.
 *
 * Pour chaque événement dû (pending|failed en retry, available_at ≤ now,
 * hors lease) :
 *   1. claim atomique pending → processing avec lease (un seul worker) ;
 *   2. résolution du consommateur ; aucun → dead-letter (permanent) ;
 *   3. exécution idempotente dans le contexte tenant de l'événement ;
 *   4. succès → sent ; erreur transitoire → retry avec backoff exponentiel
 *      (+jitter) ; erreur permanente ou attempts ≥ max → dead-letter.
 *
 * Le reclaim des leases expirées est intégré au claim (crash worker : un
 * événement dont la lease a expiré redevient éligible sans perdre son
 * budget de tentatives).
 */
final class OutboxDispatcher
{
    public function __construct(
        private readonly OutboxConsumerRegistry $registry,
        private readonly TenantManager $tenants,
    ) {}

    /**
     * @return array{claimed: int, sent: int, retried: int, dead: int}
     */
    public function dispatch(int $limit = 100): array
    {
        $stats = ['claimed' => 0, 'sent' => 0, 'retried' => 0, 'dead' => 0];

        while ($stats['claimed'] < $limit) {
            $claimed = $this->claimBatch($limit - $stats['claimed']);

            if ($claimed === []) {
                break;
            }

            foreach ($claimed as $eventId) {
                $stats['claimed']++;
                $stats = $this->processEvent((int) $eventId, $stats);
            }
        }

        return $stats;
    }

    /**
     * Réclame un lot d'événements dus : pending, ou failed en backoff arrivé
     * à échéance, ou en lease expirée (crash worker). Évite les événements
     * déjà en lease (un seul worker traite).
     *
     * @return list<int>
     */
    private function claimBatch(int $limit): array
    {
        $now = now();

        /** @var list<int> $ids */
        $ids = OutboxEvent::query()
            ->where(function ($query) use ($now): void {
                // Événements dus : pending/failed arrivés à échéance, hors lease.
                $query->where(function ($due) use ($now): void {
                    $due->whereIn('status', [OutboxEvent::STATUS_PENDING, OutboxEvent::STATUS_FAILED])
                        ->where('available_at', '<=', $now)
                        ->where(function ($lease) use ($now): void {
                            $lease->whereNull('lease_until')->orWhere('lease_until', '<=', $now);
                        });
                });
                // Ou processing orphelin : lease expirée (crash worker) — le
                // reclaim ne réinitialise PAS le budget de tentatives.
                $query->orWhere(function ($orphan) use ($now): void {
                    $orphan->where('status', OutboxEvent::STATUS_PROCESSING)
                        ->where('lease_until', '<=', $now);
                });
            })
            ->orderBy('available_at')
            ->limit($limit)
            ->lockForUpdate()
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return [];
        }

        OutboxEvent::query()
            ->whereIn('id', $ids)
            ->update([
                'status' => OutboxEvent::STATUS_PROCESSING,
                'lease_until' => now()->addMinutes(OutboxEvent::LEASE_MINUTES),
            ]);

        return $ids;
    }

    /**
     * @param  array{claimed: int, sent: int, retried: int, dead: int}  $stats
     * @return array{claimed: int, sent: int, retried: int, dead: int}
     */
    private function processEvent(int $eventId, array $stats): array
    {
        /** @var OutboxEvent|null $event */
        $event = OutboxEvent::query()->find($eventId);

        if ($event === null) {
            return $stats;
        }

        $consumer = $this->registry->consumerFor($event->event_type);

        if ($consumer === null) {
            $this->deadLetter($event, 'Aucun consommateur enregistré pour '.$event->event_type);

            $stats['dead']++;

            return $stats;
        }

        try {
            $this->runConsumer($consumer, $event);

            $event->update([
                'status' => OutboxEvent::STATUS_SENT,
                'lease_until' => null,
                'last_error' => null,
                'processed_at' => now(),
            ]);

            $stats['sent']++;

            return $stats;
        } catch (PermanentOutboxException $exception) {
            $this->deadLetter($event, $exception->getMessage());

            $stats['dead']++;

            return $stats;
        } catch (TransientOutboxException|Throwable $exception) {
            $attempts = $event->attempts + 1;

            if ($attempts >= $event->max_attempts) {
                $this->deadLetter($event, $exception->getMessage());

                $stats['dead']++;

                return $stats;
            }

            $event->update([
                'status' => OutboxEvent::STATUS_FAILED,
                'attempts' => $attempts,
                'lease_until' => null,
                'last_error' => $exception->getMessage(),
                'available_at' => now()->addSeconds(OutboxEvent::backoffForAttempt($attempts)),
            ]);

            $stats['retried']++;

            return $stats;
        }
    }

    private function runConsumer(OutboxConsumer $consumer, OutboxEvent $event): void
    {
        // Les consommateurs tenant-scoped (marqueur) tournent dans le
        // contexte du tenant (current_company + search_path). Les
        // consommateurs de plateforme tournent au search_path par défaut.
        if (! $consumer instanceof TenantScopedOutboxConsumer || $event->company_id === null) {
            $consumer->handle($event);

            return;
        }

        try {
            /** @var Company $company */
            $company = Company::query()->findOrFail($event->company_id);
        } catch (ModelNotFoundException) {
            throw new PermanentOutboxException('Société introuvable pour l\'événement outbox.');
        }

        $this->tenants->withinTenant($company, fn (): mixed => $consumer->handle($event));
    }

    private function deadLetter(OutboxEvent $event, string $reason): void
    {
        $event->update([
            'status' => OutboxEvent::STATUS_FAILED,
            'attempts' => $event->attempts + 1,
            'lease_until' => null,
            'last_error' => mb_substr($reason, 0, 1000),
            'available_at' => now()->addDay(), // rejouable via outbox:replay
        ]);
    }

    /**
     * Compteurs par statut (observabilité / backpressure).
     *
     * @return array{statuses: array<string, int>, backpressure: bool, oldest_pending_at: string|null}
     */
    public function metrics(): array
    {
        $statuses = OutboxEvent::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($v): int => (int) $v)
            ->all();

        $oldestPending = OutboxEvent::query()
            ->where('status', OutboxEvent::STATUS_PENDING)
            ->min('available_at');

        $backpressure = ($statuses[OutboxEvent::STATUS_PENDING] ?? 0) > 1000;

        return [
            'statuses' => $statuses,
            'backpressure' => $backpressure,
            'oldest_pending_at' => $oldestPending !== null ? (string) $oldestPending : null,
        ];
    }
}
