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
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('public_holidays')) {
            return;
        }

        // Nettoyage préalable des doublons : on garde la ligne la plus ancienne.
        $duplicateIds = DB::table('public_holidays as a')
            ->join('public_holidays as b', function ($join): void {
                $join->on('a.country_code', '=', 'b.country_code')
                    ->on('a.year', '=', 'b.year')
                    ->on('a.date', '=', 'b.date')
                    ->on('a.company_id', '=', 'b.company_id');
            })
            ->whereColumn('a.id', '>', 'b.id')
            ->distinct()
            ->pluck('a.id');

        foreach ($duplicateIds->chunk(500) as $chunk) {
            DB::table('public_holidays')->whereIn('id', $chunk)->delete();
        }

        // Contrainte d'unicité (toutes bases) — les NULL de company_id restent
        // distincts en SQL, donc on ajoute en plus un index partiel PostgreSQL
        // pour les fériés nationaux.
        Schema::table('public_holidays', function (Blueprint $table): void {
            $table->unique(
                ['country_code', 'year', 'date', 'company_id'],
                'public_holidays_country_year_date_company_unique'
            );
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS public_holidays_country_year_date_national_unique '
                .'ON public_holidays (country_code, year, date) WHERE company_id IS NULL'
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('public_holidays')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS public_holidays_country_year_date_national_unique');
        }

        Schema::table('public_holidays', function (Blueprint $table): void {
            $table->dropUnique('public_holidays_country_year_date_company_unique');
        });
    }
};
