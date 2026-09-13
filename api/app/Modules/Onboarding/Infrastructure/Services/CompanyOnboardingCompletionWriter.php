<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Infrastructure\Services;

use Illuminate\Support\Facades\DB;

/**
 * Écriture du drapeau « onboarding terminé » dans `public.companies.metadata`
 * (#7262).
 *
 * Vit en **Infrastructure** : la couche Application ne doit pas importer de
 * façade Laravel (garde `dev-hub/tools/check-layer-purity.sh`, issue #6568) —
 * c'est la seule raison d'être de ce service, extrait de
 * `SyncOnboardingCompletion` pour respecter la règle de pureté des couches.
 *
 * La requête est **qualifiée** (`public.companies`) sur PostgreSQL, même
 * précaution que `CompanyBrandingController::persistMetadata` : le
 * `search_path` du tenant ne doit pas détourner l'écriture vers un schéma de
 * tenant.
 */
final class CompanyOnboardingCompletionWriter
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function persist(string $companyId, array $metadata): void
    {
        $table = DB::getDriverName() === 'pgsql' ? 'public.companies' : 'companies';

        DB::table($table)
            ->where('id', $companyId)
            ->update([
                'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
    }
}
