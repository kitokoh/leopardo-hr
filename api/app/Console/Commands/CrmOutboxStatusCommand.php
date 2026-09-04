<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\CRM\Domain\Models\CrmOutboxEvent;
use Illuminate\Console\Command;

/**
 * crm:outbox-status — Observabilité de l'outbox CRM (#5866, MAT-008).
 *
 * Résumé par statut (pending / processing / sent / failed) + échantillon de
 * la dead-letter (id, type, tentatives, âge, dernière erreur TRONQUÉE et
 * redacted — jamais de PII/payload en clair dans les logs).
 *
 * Usage :
 *   php artisan crm:outbox-status                # résumé + 20 premiers DLQ
 *   php artisan crm:outbox-status --limit=5      # échantillon DLQ réduit
 *   php artisan crm:outbox-status --company=<uuid>  # focus tenant
 */
class CrmOutboxStatusCommand extends Command
{
    protected $signature = 'crm:outbox-status
        {--limit=20 : nombre d\'entrées dead-letter affichées (défaut 20)}
        {--company= : filtre company_id (UUID)}';

    protected $description = 'Affiche l\'état de l\'outbox CRM (compteurs par statut + échantillon dead-letter, redacted).';

    public function handle(): int
    {
        $query = CrmOutboxEvent::query();

        $company = $this->option('company');
        if (is_string($company) && $company !== '') {
            $query->where('company_id', $company);
        }

        $counts = (clone $query)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $this->info('[crm:outbox-status] compteurs par statut :');
        foreach ([
            CrmOutboxEvent::STATUS_PENDING,
            CrmOutboxEvent::STATUS_PROCESSING,
            CrmOutboxEvent::STATUS_SENT,
            CrmOutboxEvent::STATUS_FAILED,
        ] as $status) {
            $this->line(sprintf('  %-12s %d', $status, (int) ($counts[$status] ?? 0)));
        }

        $limit = max(1, (int) $this->option('limit'));
        $deadLetters = (clone $query)
            ->where('status', CrmOutboxEvent::STATUS_FAILED)
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        if ($deadLetters->isEmpty()) {
            $this->info('[crm:outbox-status] dead-letter vide.');

            return self::SUCCESS;
        }

        $this->warn('[crm:outbox-status] échantillon dead-letter (redacted) :');
        foreach ($deadLetters as $event) {
            /** @var CrmOutboxEvent $event */
            $error = $event->last_error !== null ? mb_substr($event->last_error, 0, 120) : '?';
            $this->line(sprintf(
                '  #%d %s (company %s, %d tentative(s), %s) : %s',
                $event->id,
                $event->event_type,
                (string) $event->company_id,
                $event->attempts,
                $event->updated_at?->diffForHumans() ?? '?',
                $error,
            ));
        }

        return self::SUCCESS;
    }
}
