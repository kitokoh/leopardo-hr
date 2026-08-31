<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\CRM\Domain\Models\CrmOutboxEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * crm:outbox-replay — Replay contrôlé des événements d'outbox en
 * dead-letter (statut `failed`) (#5866, MAT-008).
 *
 * Après revue de la DLQ (runbook RUNBOOK_FILES_CRM.md §3-§4) et correction
 * de la cause, un opérateur remet les événements `failed` en `pending` :
 * la consommation reprend au prochain `crm:outbox-dispatch`, l'idempotence
 * (clé unique (company_id, idempotency_key) + consommateurs idempotents)
 * garantit zéro double effet.
 *
 * Sûreté :
 *  - `--dry-run` (défaut affiché) : ne modifie RIEN, liste ce qui serait
 *    rejoué ;
 *  - filtres `--company` / `--event-type` / `--limit` pour un replay ciblé ;
 *  - les tentatives sont réinitialisées (attempts = 0) : un replay est un
 *    nouveau cycle de vie, borné par MAX_ATTEMPTS au prochain dispatch ;
 *  - jamais de purge automatique (décision humaine requise).
 *
 * Usage :
 *   php artisan crm:outbox-replay --dry-run --company=<uuid> --event-type=crm.x
 *   php artisan crm:outbox-replay --limit=100 --event-type=crm.x
 */
class CrmOutboxReplayCommand extends Command
{
    protected $signature = 'crm:outbox-replay
        {--limit=500 : nombre max d\'événements rejoués par passe (défaut 500)}
        {--company= : filtre company_id (UUID) — replay d\'un seul tenant}
        {--event-type= : filtre event_type — replay ciblé}
        {--dry-run : simule le replay sans modifier la base}';

    protected $description = 'Rejoue (contrôlé) les événements d\'outbox CRM en dead-letter (failed → pending, idempotent).';

    public function handle(): int
    {
        $query = CrmOutboxEvent::query()
            ->where('status', CrmOutboxEvent::STATUS_FAILED);

        $company = $this->option('company');
        if (is_string($company) && $company !== '') {
            $query->where('company_id', $company);
        }

        $eventType = $this->option('event-type');
        if (is_string($eventType) && $eventType !== '') {
            $query->where('event_type', $eventType);
        }

        $limit = max(1, (int) $this->option('limit'));
        $events = $query->orderBy('id')->limit($limit)->get();

        if ($events->isEmpty()) {
            $this->info('[crm:outbox-replay] aucun événement dead-letter à rejouer.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('[crm:outbox-replay] DRY-RUN — aucun changement effectué.');
        }

        $replayed = 0;
        foreach ($events as $event) {
            /** @var CrmOutboxEvent $event */
            if ($dryRun) {
                $this->line(sprintf(
                    '  #%d %s (company %s, %d tentative(s), DLQ depuis %s)',
                    $event->id,
                    $event->event_type,
                    (string) $event->company_id,
                    $event->attempts,
                    $event->updated_at?->toDateTimeString() ?? '?',
                ));
                $replayed++;

                continue;
            }

            // Remise en file : nouveau cycle de vie, idempotence conservée.
            $updated = DB::table('crm_outbox_events')
                ->where('id', $event->id)
                ->where('status', CrmOutboxEvent::STATUS_FAILED)
                ->update([
                    'status' => CrmOutboxEvent::STATUS_PENDING,
                    'attempts' => 0,
                    'last_error' => null,
                    'available_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($updated === 1) {
                $replayed++;
            }
        }

        $mode = $dryRun ? 'simulé' : 'rejoué';
        $this->info("[crm:outbox-replay] {$replayed} événement(s) {$mode} (failed → pending).");

        return self::SUCCESS;
    }
}
