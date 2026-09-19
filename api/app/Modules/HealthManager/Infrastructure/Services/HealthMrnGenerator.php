<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Infrastructure\Services;

use App\Modules\HealthManager\Domain\Models\HealthPatient;
use Illuminate\Support\Facades\DB;

/**
 * Génération du n° de dossier médical (MRN) — HC-003 (#7787, BC-30).
 *
 * Format `PAT-YYYY-NNNN` : séquence PAR TENANT et par année, calculée côté
 * serveur (jamais acceptée depuis la requête), zéro-paddée sur 4 chiffres
 * (déborde naturellement sur 5+ au-delà de 9999).
 *
 * Concurrence : à appeler DANS une transaction — `lockForUpdate()` sur les
 * dossiers de l'année verrouille le calcul ; la contrainte
 * UNIQUE(company_id, mrn) reste le filet de sécurité en base. Les patients
 * archivés (soft-deleted) sont inclus : un MRN n'est JAMAIS réutilisé.
 */
final class HealthMrnGenerator
{
    public function next(string $companyId): string
    {
        $prefix = 'PAT-'.now()->format('Y').'-';

        /** @var string|null $last */
        $last = HealthPatient::query()
            ->withoutGlobalScope('company')
            ->withTrashed()
            ->where('company_id', $companyId)
            ->where('mrn', 'like', $prefix.'%')
            // Tri par longueur PUIS valeur : '10000' > '9999' malgré l'ordre
            // lexicographique.
            ->orderByDesc(DB::raw('char_length(mrn)'))
            ->orderByDesc('mrn')
            ->lockForUpdate()
            ->value('mrn');

        $next = 1;
        if (is_string($last)) {
            $next = (int) substr($last, strlen($prefix)) + 1;
        }

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
