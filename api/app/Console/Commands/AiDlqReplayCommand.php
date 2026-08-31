<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\AI\Services\AiDeadLetterQueue;
use Illuminate\Console\Command;

/**
 * BC-23-D07 (issue #6239) — replay contrôlé de la dead-letter queue AI.
 *
 * Relance les jobs IA consignés en échec (statut `open`) : l'exportation
 * repasse `pending` et son job est re-dispatché. Filtres optionnels par
 * tenant ou par id d'entrée DLQ.
 */
class AiDlqReplayCommand extends Command
{
    protected $signature = 'ai:dlq:replay
        {--company-id= : Ne rejouer que les entrées de ce tenant}
        {--id= : Ne rejouer que cette entrée DLQ}
        {--limit=10 : Nombre maximal d\'entrées rejouées (1-100)}';

    protected $description = 'Rejoue les jobs IA en dead-letter queue (reset pending + re-dispatch).';

    public function handle(AiDeadLetterQueue $queue): int
    {
        $companyId = $this->option('company-id') !== null ? (string) $this->option('company-id') : null;
        $entryId = $this->option('id') !== null ? (int) $this->option('id') : null;
        $limit = max(1, min(100, (int) $this->option('limit')));

        $replayed = $queue->replay($companyId, $entryId, $limit);

        if ($replayed > 0) {
            $this->info("AI DLQ : {$replayed} job(s) relancé(s).");

            return self::SUCCESS;
        }

        $this->warn('AI DLQ : aucune entrée à rejouer.');

        return self::SUCCESS;
    }
}
