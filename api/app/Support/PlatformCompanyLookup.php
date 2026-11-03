<?php

namespace App\Support;

use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Throwable;

class PlatformCompanyLookup
{
    /**
     * Résout une société sur le schéma `public` SANS laisser fuiter le
     * `search_path` de la session.
     *
     * Issue #9115 — `SET search_path TO public` était exécuté sans
     * restauration. Sur une connexion persistante (worker, pool, requête
     * HTTP), la suite de la requête héritait de `public` : toute table
     * tenant-scoped (`shared_tenants.biometric_enrollments`, `employees`…)
     * devenait introuvable → SQLSTATE 42P01 « relation ... does not exist »,
     * puis 25P02 en cascade. Observé sur
     * `KioskEnrollmentController::resolveEnrollment()` : `kioskFromRequest()`
     * appelle ce lookup APRÈS le `finally` du middleware
     * `ResolveKioskDevice`, donc la restauration du middleware ne couvrait
     * pas cet appel.
     *
     * Même pattern try/finally que `ResolveKioskDevice` / `KioskController`
     * (#2689, #3368) : le search_path est TOUJOURS rendu à son état d'entrée.
     */
    public static function findOrFail(string $companyId): Company
    {
        $previous = self::currentSearchPath();

        DB::statement('SET search_path TO public');

        try {
            $row = DB::table(self::table())
                ->where('id', $companyId)
                ->first();
        } finally {
            if ($previous !== null) {
                DB::statement('SET search_path TO '.$previous);
            }
        }

        if (! $row) {
            throw (new ModelNotFoundException)->setModel(Company::class, [$companyId]);
        }

        return (new Company)->newFromBuilder((array) $row);
    }

    /**
     * `search_path` courant de la session, ou null si indisponible (driver
     * non PostgreSQL, connexion absente) — dans ce cas on ne touche à rien.
     */
    private static function currentSearchPath(): ?string
    {
        if (DB::getDriverName() !== 'pgsql') {
            return null;
        }

        try {
            $row = DB::selectOne('SHOW search_path');
        } catch (Throwable) {
            return null;
        }

        if (! is_object($row) || ! property_exists($row, 'search_path')) {
            return null;
        }

        $value = (string) $row->search_path;

        return $value === '' ? null : $value;
    }

    private static function table(): string
    {
        return DB::getDriverName() === 'pgsql' ? 'public.companies' : 'companies';
    }
}
