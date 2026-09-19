<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Infrastructure\Services;

use App\Modules\HealthManager\Domain\Models\HealthPatient;
use Illuminate\Support\Facades\DB;

/**
 * Génération du MRN patient `PAT-YYYY-NNNN` — HC-003 (#7787).
 *
 * Séquence par tenant ET par année (spec §4), générée côté serveur.
 * Race-safety : la lecture du dernier MRN de l'année verrouille les lignes
 * candidates (`lockForUpdate`, pattern numérotation Edu) et DOIT être
 * exécutée dans la transaction qui insère le patient ; en dernier recours,
 * l'unique (company_id, mrn) en base rejette tout doublon concurrent.
 */
class HealthPatientNumberService
{
    /**
     * Prochain MRN pour le tenant (à appeler DANS une transaction).
     */
    public function nextMrn(string $companyId): string
    {
        $year = now()->format('Y');
        $prefix = 'PAT-'.$year.'-';

        // Verrouille les lignes du tenant/année (SELECT ... FOR UPDATE) et
        // lit le dernier numéro de séquence — tri NUMÉRIQUE (robuste même
        // au-delà de 4 chiffres, où l'ordre lexicographique se casse).
        // NB : Postgres interdit FOR UPDATE + agrégat, d'où ORDER BY/LIMIT.
        /** @var string|null $latest */
        $latest = HealthPatient::query()
            ->where('company_id', $companyId)
            ->where('mrn', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByRaw(
                "cast(substring(mrn from '^PAT-\\d{4}-(\\d+)$') as integer) desc"
            )
            ->value('mrn');

        $sequence = $latest === null
            ? 1
            : ((int) substr($latest, strlen($prefix))) + 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Crée un patient avec un MRN serveur, de façon atomique.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createWithMrn(string $companyId, array $attributes): HealthPatient
    {
        return DB::transaction(function () use ($companyId, $attributes): HealthPatient {
            /** @var HealthPatient $patient */
            $patient = HealthPatient::query()->create(array_merge($attributes, [
                'company_id' => $companyId,
                'mrn' => $this->nextMrn($companyId),
            ]));

            return $patient;
        });
    }
}
