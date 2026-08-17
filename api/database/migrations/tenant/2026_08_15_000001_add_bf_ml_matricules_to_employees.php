<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CEDEAO (#2158) — matricules CNSS Burkina Faso / INPS Mali.
 *
 * Migration additive sur `employees` :
 *   - `cnss_bf_matricule` : numéro d'immatriculation CNSS Burkina Faso ;
 *   - `inps_ml_matricule` : numéro d'immatriculation INPS Mali.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('employees')) {
            return;
        }

        if (! schemaHasColumn('employees', 'cnss_bf_matricule')) {
            Schema::table('employees', function (Blueprint $blueprint): void {
                $blueprint->string('cnss_bf_matricule', 50)->nullable();
            });
        }

        if (! schemaHasColumn('employees', 'inps_ml_matricule')) {
            Schema::table('employees', function (Blueprint $blueprint): void {
                $blueprint->string('inps_ml_matricule', 50)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (! schemaTableExists('employees')) {
            return;
        }

        foreach (['cnss_bf_matricule', 'inps_ml_matricule'] as $column) {
            if (Schema::hasColumn('employees', $column)) {
                Schema::table('employees', function (Blueprint $blueprint) use ($column): void {
                    $blueprint->dropColumn($column);
                });
            }
        }
    }
};
