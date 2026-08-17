<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CEDEAO (#1830) — matricules CNSS CI / IPRES SN + catégorie IPRES.
 *
 * Migration additive sur `employees` :
 *   - `cnss_ci_matricule` : numéro d'immatriculation CNSS Côte d'Ivoire ;
 *   - `ipres_matricule` : numéro d'immatriculation IPRES Sénégal ;
 *   - `ipres_category` : 'general' | 'cadre' (la cotisation T2 de l'IPRES
 *     ne s'applique qu'aux cadres) — défaut 'general'.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('employees')) {
            return;
        }

        if (! schemaHasColumn('employees', 'cnss_ci_matricule')) {
            Schema::table('employees', function (Blueprint $blueprint): void {
                $blueprint->string('cnss_ci_matricule', 50)->nullable();
            });
        }

        if (! schemaHasColumn('employees', 'ipres_matricule')) {
            Schema::table('employees', function (Blueprint $blueprint): void {
                $blueprint->string('ipres_matricule', 50)->nullable();
            });
        }

        if (! schemaHasColumn('employees', 'ipres_category')) {
            Schema::table('employees', function (Blueprint $blueprint): void {
                $blueprint->string('ipres_category', 20)->default('general'); // general | cadre
            });
        }
    }

    public function down(): void
    {
        if (! schemaTableExists('employees')) {
            return;
        }

        foreach (['cnss_ci_matricule', 'ipres_matricule', 'ipres_category'] as $column) {
            if (Schema::hasColumn('employees', $column)) {
                Schema::table('employees', function (Blueprint $blueprint) use ($column): void {
                    $blueprint->dropColumn($column);
                });
            }
        }
    }
};
