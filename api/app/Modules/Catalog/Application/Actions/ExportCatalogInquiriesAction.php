<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Models\CatalogInquiry;
use Illuminate\Database\Eloquent\Collection;

/**
 * Cas d'usage « exporter les demandes de devis » (BOS-024f, #8217).
 *
 * Extrait de `CatalogInquiryController::export` : jeu de donnees borne
 * (5 000 lignes) filtre par statut, ordonne du plus recent au plus ancien.
 * La mise en forme CSV et le telechargement restent dans la couche HTTP
 * (StreamedResponse) — l'Action ne produit que la collection.
 */
final class ExportCatalogInquiriesAction
{
    public const EXPORT_LIMIT = 5000;

    /**
     * @return Collection<int, CatalogInquiry>
     */
    public function execute(
        string $companyId,
        ?string $status = null,
        int $limit = self::EXPORT_LIMIT
    ): Collection {
        return CatalogInquiry::query()
            ->where('company_id', $companyId)
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
