<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Infrastructure\Services;

use Illuminate\Support\Facades\DB;

/**
 * Écriture de `public.companies.onboarding_state` (BOS-O-06 / #8627).
 *
 * Vit en **Infrastructure** pour la même raison que
 * `CompanyOnboardingCompletionWriter` : la couche Application n'importe pas de
 * façade Laravel (garde `dev-hub/tools/check-layer-purity.sh`, issue #6568).
 *
 * La requête est **qualifiée** (`public.companies`) sur PostgreSQL — même
 * précaution que les autres écritures de registre : le `search_path` du tenant
 * ne doit pas détourner l'écriture vers une table shadow de schéma tenant.
 */
final class CompanyOnboardingStateWriter
{
    /**
     * Persiste l'état et renvoie la valeur réellement écrite (horodatage posé
     * ici : l'Application ne touche pas aux helpers de framework).
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function persist(string $companyId, array $state): array
    {
        $state['updated_at'] = now()->toIso8601String();

        $table = DB::getDriverName() === 'pgsql' ? 'public.companies' : 'companies';

        DB::table($table)
            ->where('id', $companyId)
            ->update([
                'onboarding_state' => json_encode($state, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);

        return $state;
    }
}
