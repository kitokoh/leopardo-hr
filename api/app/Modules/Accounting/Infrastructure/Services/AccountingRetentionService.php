<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Services;

use App\Modules\Accounting\Domain\Enums\DocumentStatus;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * #5273 — Rétention légale des documents comptables.
 *
 * Seuls les documents FINALISÉS (paid, cancelled, overdue) sont éligibles à
 * la purge : un brouillon ou un document envoyé reste potentiellement en
 * évolution. Le cutoff court depuis `issue_date` (l'émission), pas la
 * création. Les lignes et paiements suivent le document (FK cascade #5221) ;
 * le PDF archivé est supprimé du storage avec le document.
 */
class AccountingRetentionService
{
    /**
     * @param  list<DocumentStatus>  $purgeableStatuses
     */
    public function __construct(
        private readonly array $purgeableStatuses = [DocumentStatus::Paid, DocumentStatus::Cancelled, DocumentStatus::Overdue],
    ) {}

    /**
     * Nombre de documents éligibles à la purge (sans rien supprimer).
     */
    public function countEligible(int $months, Carbon $now = new Carbon): int
    {
        return $this->query($months, $now)->count();
    }

    /**
     * Purge les documents finalisés antérieurs au cutoff. Retourne les
     * documents supprimés (modèles) pour rapport.
     *
     * @return list<AccountingDocument>
     */
    public function purge(int $months, bool $dryRun = false, Carbon $now = new Carbon): array
    {
        $documents = $this->query($months, $now)->get();

        foreach ($documents as $document) {
            if ($document->pdf_path !== null && $document->pdf_path !== '') {
                Storage::disk('local')->delete($document->pdf_path);
            }

            if (! $dryRun) {
                $document->delete(); // lignes + paiements en cascade
            }
        }

        return array_values($documents->all());
    }

    /**
     * @return Builder<AccountingDocument>
     */
    private function query(int $months, Carbon $now)
    {
        $cutoff = $now->copy()->subMonths(max(1, $months))->toDateString();
        $statuses = array_map(static fn (DocumentStatus $status): string => $status->value, $this->purgeableStatuses);

        return AccountingDocument::query()
            ->withoutGlobalScopes()
            ->whereIn('status', $statuses)
            ->where('issue_date', '<', $cutoff);
    }
}
