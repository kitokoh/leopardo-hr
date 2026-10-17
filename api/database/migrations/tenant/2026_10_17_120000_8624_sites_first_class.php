<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BOS-T-01 / #8624 — l'établissement devient une entité de premier ordre.
 *
 * Constat de l'audit (`docs/mission-business-os/08_gap_matrix.md`, ligne T-01) :
 * `sites` ne portait que `name`, `address` et un point GPS. Un tenant pouvait
 * donc exister sans qu'aucune de ses données métier ne soit rattachable à un
 * lieu d'exploitation identifiable : pas de code stable (impossible de référencer
 * « le site principal » dans un import, une caisse ou un reçu), pas de type
 * (restaurant / station / boutique), pas de statut (un établissement fermé
 * restait indistinguable d'un établissement actif), pas de fuseau ni de devise
 * (un groupe multi-pays était condamné à la devise du tenant), et aucun index
 * sur `company_id` alors que TOUTES les requêtes filtrent par tenant.
 *
 * La migration est ADDITIVE et idempotente : colonnes nullables (sauf `status`
 * qui a un défaut), aucun remplissage rétroactif destructif, rejouable sans
 * erreur. `down()` ne supprime que ce que `up()` a ajouté.
 *
 * Unicité : `(company_id, code)` est UNIQUE mais PARTIEL (`WHERE code IS NOT
 * NULL`) — deux tenants distincts peuvent utiliser le même code, et les
 * établissements historiques sans code ne se heurtent pas entre eux sur NULL.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const COLUMNS = ['code', 'site_type', 'status', 'timezone', 'currency', 'updated_at'];

    public function up(): void
    {
        $schema = resolveTableSchema('sites');

        if ($schema === null) {
            // Le schéma tenant n'est pas monté (contexte public) : rien à faire.
            return;
        }

        foreach (self::COLUMNS as $column) {
            if ($this->columnExists($schema, $column)) {
                continue;
            }

            Schema::table('sites', function (Blueprint $table) use ($column): void {
                match ($column) {
                    'code' => $table->string('code', 30)->nullable(),
                    'site_type' => $table->string('site_type', 30)->nullable(),
                    'status' => $table->string('status', 20)->default('active'),
                    'timezone' => $table->string('timezone', 50)->nullable(),
                    'currency' => $table->char('currency', 3)->nullable(),
                    'updated_at' => $table->timestampTz('updated_at')->nullable(),
                };
            });
        }

        // Index de tenant : toutes les requêtes filtrent par `company_id`, aucun
        // index ne couvrait cette colonne (le `->index()` d'origine portait sur
        // la création de la table, supprimé par les fixtures de test).
        if (! $this->indexExists($schema, 'sites_company_id_idx')) {
            DB::statement("CREATE INDEX IF NOT EXISTS sites_company_id_idx ON {$schema}.sites (company_id)");
        }

        // Code d'établissement stable et unique PAR tenant uniquement.
        if (! $this->indexExists($schema, 'sites_company_code_uidx')) {
            DB::statement(
                "CREATE UNIQUE INDEX IF NOT EXISTS sites_company_code_uidx
                   ON {$schema}.sites (company_id, code)
                 WHERE code IS NOT NULL"
            );
        }
    }

    public function down(): void
    {
        $schema = resolveTableSchema('sites');

        if ($schema === null) {
            return;
        }

        foreach (['sites_company_code_uidx', 'sites_company_id_idx'] as $index) {
            if ($this->indexExists($schema, $index)) {
                DB::statement("DROP INDEX IF EXISTS {$schema}.{$index}");
            }
        }

        foreach (self::COLUMNS as $column) {
            if (! $this->columnExists($schema, $column)) {
                continue;
            }

            Schema::table('sites', function (Blueprint $table) use ($column): void {
                $table->dropColumn($column);
            });
        }
    }

    private function columnExists(string $schema, string $column): bool
    {
        $row = DB::selectOne(
            'SELECT 1 FROM information_schema.columns
              WHERE table_schema = ? AND table_name = ? AND column_name = ?',
            [$schema, 'sites', $column]
        );

        return $row !== null;
    }

    private function indexExists(string $schema, string $index): bool
    {
        $row = DB::selectOne(
            'SELECT 1 FROM pg_indexes WHERE schemaname = ? AND indexname = ?',
            [$schema, $index]
        );

        return $row !== null;
    }
};
