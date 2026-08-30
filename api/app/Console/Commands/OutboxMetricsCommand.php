<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Outbox\Infrastructure\Services\OutboxDispatcher;
use Illuminate\Console\Command;

/**
 * MAT-008 (#5866) — Observabilité de l'outbox générique (backpressure).
 *
 * Usage : php artisan outbox:metrics
 * Scheduler : workflow `queue-supervision` (cron 5 min) pour alerter sur le
 * lag et la backpressure (file > 1 000 pending).
 */
class OutboxMetricsCommand extends Command
{
    protected $signature = 'outbox:metrics';

    protected $description = 'Affiche les compteurs de l\'outbox générique (statuts, backpressure, plus ancien pending).';

    public function handle(OutboxDispatcher $dispatcher): int
    {
        $metrics = $dispatcher->metrics();

        $this->table(
            ['statut', 'nombre'],
            collect($metrics['statuses'])->map(fn (int $count, string $status): array => [$status, (string) $count])->values()->all(),
        );

        $this->info('Backpressure : '.($metrics['backpressure'] ? 'OUI (> 1 000 pending)' : 'non'));
        $this->info('Plus ancien événement pending : '.($metrics['oldest_pending_at'] ?? 'aucun'));

        return self::SUCCESS;
    }
}
