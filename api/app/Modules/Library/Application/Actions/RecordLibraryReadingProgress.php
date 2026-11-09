<?php

declare(strict_types=1);

namespace App\Modules\Library\Application\Actions;

use App\Modules\Library\Domain\Models\LibraryReadingProgress;
use App\Modules\Library\Domain\Models\LibraryResource;

/**
 * Enregistre la progression de lecture d'un employé (BC-40 LIBRARY).
 *
 * Action de la couche Application (1 action = 1 execute(), convention du
 * dépôt). Elle borne la page à `[1, total_pages]` — une valeur aberrante
 * envoyée par un client ne peut donc pas corrompre la position enregistrée —
 * puis fait un upsert idempotent sur la clé
 * `(company_id, employee_id, resource_id)`.
 *
 * ⚠️ ISOLATION TENANT : `company_id` et `employee_id` viennent de la SESSION
 * authentifiée, jamais du payload. Le hook `creating` de `BelongsToCompany`
 * force en plus `company_id` depuis le tenant actif (anti-spoof #7646).
 */
final class RecordLibraryReadingProgress
{
    public function execute(
        LibraryResource $resource,
        int $employeeId,
        int $page,
        ?int $totalPages = null,
    ): LibraryReadingProgress {
        $total = $totalPages !== null && $totalPages > 0 ? $totalPages : null;
        $bounded = $this->clamp($page, $total);

        /** @var LibraryReadingProgress $progress */
        $progress = LibraryReadingProgress::query()->updateOrCreate(
            [
                'employee_id' => $employeeId,
                'resource_id' => (int) $resource->id,
            ],
            [
                'page' => $bounded,
                'total_pages' => $total,
            ],
        );

        return $progress;
    }

    /**
     * Borne la page dans `[1, total]` (ou `>= 1` si le total est inconnu).
     */
    private function clamp(int $page, ?int $total): int
    {
        if ($page < 1) {
            return 1;
        }

        if ($total !== null && $page > $total) {
            return $total;
        }

        return $page;
    }
}
