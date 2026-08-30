<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Platform\Domain\Models\PlatformOutboxEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * platform:outbox-replay — Rejeu contrôlé des événements d'outbox plateforme
 * (MAT-008 #5866).
 *
 * Remet `failed` → `pending` (available_at = now) pour un événement précis
 * (--id), un type (--event-type) ou l'ensemble des dead-letters
 * (--limit). Le rejeu est SANS risque de doublon : l'idempotence est portée
 * par la clé unique (company_id, idempotency_key) et par les consommateurs.
 *
 * Usage :
 *   php artisan platform:outbox-replay --id=42
 *   php artisan platform:outbox-replay --event-type=platform.subscription.paid
 *   php artisan platform:outbox-replay --limit=50
 */
class PlatformOutboxReplayCommand extends Command
{
    protected $signature = 'platform:outbox-replay
        {--id= : identifiant d\'un événement précis à rejouer}
        {--event-type= : ne rejouer que ce type d\'événement}
        {--limit=50 : nombre max d\'événements rejoués par passe (défaut 50)}';

    protected $description = 'Rejoue les événements d\'outbox plateforme en échec (dead-letter → pending).';

    public function handle(): int
    {
        $query = DB::table('platform_outbox_events')
            ->where('status', PlatformOutboxEvent::STATUS_FAILED);

        $id = $this->option('id');
        $eventType = $this->option('event-type');
        $limit = (int) $this->option('limit');

        if ($id !== null) {
            $query->where('id', (int) $id);
        }

        if ($eventType !== null && $eventType !== '') {
            $query->where('event_type', $eventType);
        }

        $count = $query->limit($limit)->update([
            'status' => PlatformOutboxEvent::STATUS_PENDING,
            'attempts' => 0,
            'last_error' => null,
            'available_at' => now(),
            'updated_at' => now(),
        ]);

        $this->info("[platform:outbox-replay] {$count} événement(s) rejoué(s).");

        return self::SUCCESS;
    }
}
