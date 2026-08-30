<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Outbox\Domain\Models\OutboxEvent;
use Illuminate\Console\Command;

/**
 * MAT-008 (#5866) — Replay contrôlé d'événements d'outbox.
 *
 * Remet en file (pending, disponible immédiatement) des événements en
 * échec, éventuellement filtrés par type et par gravité :
 *   --status=failed : tous les événements en échec (retry + dead-letter) ;
 *   --status=retry  : seulement ceux qui ont encore du budget de tentatives ;
 *   --status=dead   : seulement la dead-letter (attempts >= max_attempts).
 *
 * Le replay est BORNÉ (`--limit`, défaut 100) et préserve l'historique
 * (attempts et last_error conservés — pas de reset silencieux).
 *
 * Usage :
 *   php artisan outbox:replay --status=dead --type=billing.subscription_paid
 *   php artisan outbox:replay --status=retry --limit=500
 */
class OutboxReplayCommand extends Command
{
    protected $signature = 'outbox:replay
        {--status=failed : failed (tous) | retry (budget restant) | dead (dead-letter)}
        {--type= : filtre sur le type d\'événement (optionnel)}
        {--limit=100 : nombre max d\'événements rejoués (défaut 100)}';

    protected $description = 'Replay contrôlé d\'événements d\'outbox en échec — borné, préserve attempts/last_error.';

    public function handle(): int
    {
        $status = (string) $this->option('status');

        if (! in_array($status, ['failed', 'retry', 'dead'], true)) {
            $this->error('Statut invalide (attendu : failed | retry | dead).');

            return self::FAILURE;
        }

        $query = OutboxEvent::query()->where('status', OutboxEvent::STATUS_FAILED);

        if ($status === 'retry') {
            $query->whereColumn('attempts', '<', 'max_attempts');
        } elseif ($status === 'dead') {
            $query->whereColumn('attempts', '>=', 'max_attempts');
        }

        $type = $this->option('type');
        if (is_string($type) && $type !== '') {
            $query->where('event_type', $type);
        }

        $count = $query
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->update([
                'status' => OutboxEvent::STATUS_PENDING,
                'available_at' => now(),
                'lease_until' => null,
            ]);

        $this->info(sprintf(
            '[outbox:replay] %d événement(s) rejoué(s) (%s%s).',
            $count,
            $status,
            $type !== '' ? ', type='.$type : '',
        ));

        return self::SUCCESS;
    }
}
