<?php

namespace App\Services;

use App\Models\Company;
use Closure;
use Illuminate\Support\Facades\DB;

class TenantManager
{
    private string $previousPath = 'public';

    private ?Company $previousCompany = null;

    /**
     * Bascule la connexion sur le schema d'un tenant specifique.
     */
    public function setTenant(Company $company): void
    {
        $this->previousCompany = app()->bound('current_company')
            ? app('current_company')
            : null;

        if (DB::getDriverName() === 'pgsql') {
            $currentPath = DB::selectOne('SHOW search_path');
            $this->previousPath = $currentPath->search_path ?? 'public';

            DB::statement('SET search_path TO '.$company->getSafeSearchPath());
        }

        app()->instance('current_company', $company);
    }

    /**
     * Restaure le chemin de recherche precedent.
     */
    public function resetToPrevious(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET search_path TO '.$this->previousPath);
        }

        $this->restoreCompanyContext($this->previousCompany);
        $this->previousCompany = null;
    }

    /**
     * Execute une callback dans le contexte d'un tenant et restaure le contexte ensuite.
     */
    public function withinTenant(Company $company, Closure $cb): mixed
    {
        $oldPath = 'public';
        $oldCompany = app()->bound('current_company')
            ? app('current_company')
            : null;

        if (DB::getDriverName() === 'pgsql') {
            $currentPath = DB::selectOne('SHOW search_path');
            $oldPath = $currentPath->search_path ?? 'public';
        }

        $this->setTenant($company);

        try {
            return $cb();
        } finally {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('SET search_path TO '.$oldPath);
            }

            $this->restoreCompanyContext($oldCompany);
            $this->previousCompany = null;
        }
    }

    private function restoreCompanyContext(?Company $company): void
    {
        if ($company) {
            app()->instance('current_company', $company);

            return;
        }

        app()->forgetInstance('current_company');
    }
}
