<?php

namespace App\Services;

use App\Models\Company;
use Closure;
use Illuminate\Support\Facades\DB;

class TenantManager
{
    private string $previousPath = 'public';

    /**
     * Bascule la connexion sur le schéma d'un tenant spécifique.
     */
    public function setTenant(Company $company): void
    {
        // On récupère le chemin actuel avant de basculer
        if (DB::getDriverName() === 'pgsql') {
            $currentPath = DB::selectOne('SHOW search_path');
            $this->previousPath = $currentPath->search_path ?? 'public';
            
            $schema = preg_replace('/[^a-zA-Z0-9_]/', '', $company->schema_name ?: 'shared_tenants') ?: 'shared_tenants';
            DB::statement('SET search_path TO "' . $schema . '",public');
        }

        app()->instance('current_company', $company);
    }

    /**
     * Restaure le chemin de recherche précédent.
     */
    public function resetToPrevious(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET search_path TO ' . $this->previousPath);
        }
    }

    /**
     * Exécute une callback dans le contexte d'un tenant et restaure le contexte ensuite.
     */
    public function withinTenant(Company $company, Closure $cb): mixed
    {
        $oldPath = 'public';
        if (DB::getDriverName() === 'pgsql') {
            $currentPath = DB::selectOne('SHOW search_path');
            $oldPath = $currentPath->search_path ?? 'public';
        }

        $this->setTenant($company);

        try {
            return $cb();
        } finally {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('SET search_path TO ' . $oldPath);
            }
        }
    }
}
