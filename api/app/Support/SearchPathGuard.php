<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Exécute du code sous un `search_path` donné, puis **rend toujours** la
 * session à son état d'entrée.
 *
 * Issue #9115 — `SET search_path TO public` exécuté sans restauration laissait
 * la connexion sur `public` pour le reste de la requête (ou du worker). Toute
 * table tenant-scoped devenait alors introuvable : SQLSTATE 42P01
 * « relation ... does not exist », puis 25P02 en cascade. Le bug n'était pas
 * isolé : le même motif apparaissait dans une vingtaine de fichiers.
 *
 * Ce helper centralise le try/finally pour qu'il n'y ait plus qu'un seul
 * endroit à corriger, et qu'un oubli devienne visible (voir
 * `dev-hub/tools/check-search-path-guard.py`).
 */
final class SearchPathGuard
{
    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function run(string $searchPath, Closure $callback): mixed
    {
        $previous = self::current();

        DB::statement('SET search_path TO '.$searchPath);

        try {
            return $callback();
        } finally {
            if ($previous !== null) {
                DB::statement('SET search_path TO '.$previous);
            }
        }
    }

    /**
     * `search_path` courant de la session, ou null si indisponible (driver non
     * PostgreSQL, connexion absente) — dans ce cas on ne touche à rien.
     */
    public static function current(): ?string
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
}
