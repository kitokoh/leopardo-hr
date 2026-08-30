<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Outbox\Infrastructure\Services\OutboxDispatcher;
use Illuminate\Console\Command;

/**
 * MAT-008 (#5866) — Consomme l'outbox générique des événements plateforme.
 *
 * Usage : php artisan outbox:dispatch --limit=100
 * Scheduler : toutes les minutes (workflow `queue-supervision` + schedule).
 */
class OutboxDispatchCommand extends Command
{
    protected $signature = 'outbox:dispatch
        {--limit=100 : nombre max d\'événements par passe (défaut 100)}';

    protected $description = 'Consomme les événements d\'outbox générique dus (idempotent, retry avec backoff, dead-letter).';

    public function handle(OutboxDispatcher $dispatcher): int
    {
        $stats = $dispatcher->dispatch((int) $this->option('limit'));

        $this->info(sprintf(
            '[outbox:dispatch] %d réclamé(s) — %d envoyé(s), %d en retry, %d en dead-letter.',
            $stats['claimed'],
            $stats['sent'],
            $stats['retried'],
            $stats['dead'],
        ));

        return self::SUCCESS;
    }
}
