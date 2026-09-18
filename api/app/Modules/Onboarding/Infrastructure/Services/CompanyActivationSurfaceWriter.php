<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Infrastructure\Services;

use Illuminate\Support\Facades\DB;

/**
 * #7493 — écriture de la surface d'activation d'une société :
 * `public.companies.metadata` (état de l'entretien + `metadata.modules`) et
 * `public.companies.features` (flags plateforme).
 *
 * Vit en **Infrastructure** pour la même raison que
 * `CompanyOnboardingCompletionWriter` : la couche Application n'importe pas de
 * façade Laravel (garde `dev-hub/tools/check-layer-purity.sh`, #6568).
 *
 * La requête est **qualifiée** (`public.companies`) sur PostgreSQL — même
 * précaution que `CompanyModuleController::persist` : le `search_path` du
 * tenant ne doit pas détourner l'écriture vers un schéma de tenant.
 */
final class CompanyActivationSurfaceWriter
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $features
     */
    public function persist(string $companyId, array $metadata, array $features): void
    {
        $table = DB::getDriverName() === 'pgsql' ? 'public.companies' : 'companies';

        DB::table($table)
            ->where('id', $companyId)
            ->update([
                'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
                'features' => json_encode($features, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
    }
}
