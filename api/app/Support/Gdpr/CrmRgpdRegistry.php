<?php

declare(strict_types=1);

namespace App\Support\Gdpr;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Config;

/**
 * CrmRgpdRegistry — registre versionné des traitements PII du CRM client
 * (issue #5739).
 *
 * Source : `api/config/crm-rgpd.php` (manifeste versionné) et
 * `docs/security/CRM_REGISTRE_RGPD.md` (registre documentaire lié aux tables).
 *
 * Règles :
 *  - toute donnée PII CRM doit être déclarée dans le manifeste avant d'être
 *    collectée (même règle que le registre RH #5713) ;
 *  - le registre est lié aux tables : chaque entrée référence une table tenant
 *    et ses colonnes PII typées (email|phone|name|generic) ;
 *  - aucune valeur PII réelle ne doit transiter par les logs (masquage).
 */
final class CrmRgpdRegistry
{
    /** Version courante du manifeste (bump à chaque évolution de traitement). */
    public static function version(): int
    {
        $version = Config::get('crm-rgpd.version');

        return is_int($version) ? $version : 1;
    }

    /**
     * Toutes les entrées du registre.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function entries(): array
    {
        $registry = Config::get('crm-rgpd.registry', []);

        return is_array($registry) ? $registry : [];
    }

    /**
     * Une entrée du registre par table.
     *
     * @return array<string, mixed>|null
     */
    public static function entryForTable(string $table): ?array
    {
        $entry = self::entries()[$table] ?? null;

        return is_array($entry) ? $entry : null;
    }

    /**
     * Vrai si la table est déclarée au registre ET existe dans le schéma
     * courant (lien registre → table réellement vérifiable).
     */
    public static function isRegisteredAndPresent(string $table): bool
    {
        return self::entryForTable($table) !== null && Schema::hasTable($table);
    }

    /**
     * Retourne les colonnes PII (colonne => type) d'une entrée.
     *
     * @return array<string, string>
     */
    public static function piiColumns(string $table): array
    {
        $entry = self::entryForTable($table);
        $columns = is_array($entry) ? ($entry['pii_columns'] ?? []) : [];

        return is_array($columns) ? $columns : [];
    }

    /**
     * Tables du registre absentes du schéma courant (elles arrivent avec le
     * socle V0 — la commande d'anonymisation les ignore proprement).
     *
     * @return list<string>
     */
    public static function missingTables(): array
    {
        $missing = [];
        foreach (array_keys(self::entries()) as $table) {
            if (! Schema::hasTable($table)) {
                $missing[] = $table;
            }
        }

        return $missing;
    }
}
