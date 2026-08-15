<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #1937 — CRUD jours fériés : unicité (country_code, year, date,
 * company_id). Deux lignes identiques pour le même pays/année/date/société
 * créaient des doublons (férié national : company_id NULL).
 *
 * Revue lead : cette migration vit dans le jeu PUBLIC (la table
 * `public_holidays` est partagée entre tenants — `public/2026_08_14_000002`).
 * Placée en `tenant/`, elle n'aurait jamais tourné (gardes `Schema::hasTable`
 * vues depuis `shared_tenants` → false) et la contrainte DB n'existait nulle
 * part (F-17, issue #1933).
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = resolveTableSchema('public_holidays');

        if ($schema === null) {
            return;
        }

        $qualified = $schema.'.public_holidays';

        // Nettoyage préalable des doublons : on garde la ligne la plus ancienne.
        // `IS NOT DISTINCT FROM` : deux lignes nationales (company_id NULL)
        // doivent être comparées comme égales — `=` ne matche jamais NULL.
        $duplicateIds = DB::table('public_holidays as a')
            ->join('public_holidays as b', function ($join): void {
                $join->on('a.country_code', '=', 'b.country_code')
                    ->on('a.year', '=', 'b.year')
                    ->on('a.date', '=', 'b.date')
                    ->whereRaw('a.company_id IS NOT DISTINCT FROM b.company_id');
            })
            ->whereColumn('a.id', '>', 'b.id')
            ->distinct()
            ->pluck('a.id');

        foreach ($duplicateIds->chunk(500) as $chunk) {
            DB::table('public_holidays')->whereIn('id', $chunk)->delete();
        }

        // Contrainte d'unicité (toutes bases) — les NULL de company_id restent
        // distincts en SQL, donc on ajoute en plus un index partiel PostgreSQL
        // pour les fériés nationaux. Nom qualifié (F-17).
        // Idempotence (#2326) : le test « artisan migrate is idempotent »
        // rejoue les migrations public/ sans passer par la table `migrations`
        // (up() manuels du test de placement) → garder sur l'existence de la
        // contrainte via information_schema, pas seulement sur la table.
        $constraintExists = DB::table('information_schema.table_constraints')
            ->where('constraint_name', 'public_holidays_country_year_date_company_unique')
            ->where('table_schema', $schema)
            ->exists();

        if (! $constraintExists) {
            Schema::table($qualified, function (Blueprint $table): void {
                $table->unique(
                    ['country_code', 'year', 'date', 'company_id'],
                    'public_holidays_country_year_date_company_unique'
                );
            });
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS public_holidays_country_year_date_national_unique '
                ."ON {$qualified} (country_code, year, date) WHERE company_id IS NULL"
            );
        }
    }

    public function down(): void
    {
        $schema = resolveTableSchema('public_holidays');

        if ($schema === null) {
            return;
        }

        $qualified = $schema.'.public_holidays';

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS public_holidays_country_year_date_national_unique');
        }

        Schema::table($qualified, function (Blueprint $table): void {
            $table->dropUnique('public_holidays_country_year_date_company_unique');
        });
    }
};
