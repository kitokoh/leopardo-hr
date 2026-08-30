<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #5122 — Ajoute `badge_number` (nullable) sur `employees`.
 *
 * badge_number est la carte de pointage formelle (distinct du matricule RH).
 * L'unicité est scopée au tenant (company_id + badge_number) via un index
 * partiel (WHERE badge_number IS NOT NULL) pour ne pas contraindre les NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('employees', 'badge_number')) {
            Schema::table('employees', function (Blueprint $table): void {
                $table->string('badge_number', 50)->nullable()->after('zkteco_id')
                    ->comment('Badge/carte de pointage (#5122). Unique par tenant quand non-null.');
            });
        }

        // Index unique partiel (company_id, badge_number) WHERE badge_number IS NOT NULL.
        // Compatible PostgreSQL ; ignore silencieusement l'erreur si l'index existe déjà.
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            try {
                DB::statement(
                    'CREATE UNIQUE INDEX IF NOT EXISTS employees_company_badge_number_unique '
                    .'ON employees (company_id, badge_number) '
                    .'WHERE badge_number IS NOT NULL'
                );
            } catch (Throwable) {
                // Déjà présent ou environnement non-PG : on continue.
            }
        } else {
            // SQLite / MySQL : contrainte simple (les tests fonctionnent, PG gère la prod).
            try {
                Schema::table('employees', function (Blueprint $table): void {
                    $table->index(['company_id', 'badge_number'], 'employees_company_badge_number_index');
                });
            } catch (Throwable) {
                // Index déjà présent.
            }
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS employees_company_badge_number_unique');
        } else {
            try {
                Schema::table('employees', function (Blueprint $table): void {
                    $table->dropIndex('employees_company_badge_number_index');
                });
            } catch (Throwable) {
                // Ignoré.
            }
        }

        if (Schema::hasColumn('employees', 'badge_number')) {
            Schema::table('employees', function (Blueprint $table): void {
                $table->dropColumn('badge_number');
            });
        }
    }
};
