<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #2117 — parts fiscales / charges de famille sur l'employé.
 *
 * Colonne `family_parts` (décimal) : nombre de parts fiscales de l'employé
 * pour les réductions d'impôt familiales (CI — RICF art. 120 CGI : 1 à 5
 * parts ; défaut 1 = célibataire sans enfant). Additif, idempotent
 * (garde `schemaHasColumn` F-17). Le moteur lit ce champ dans
 * `PayrollCalculator::calculateSlip()` et l'applique via
 * `CountryRules::withFamilyParts()` — aucun changement de comportement
 * tant que la valeur reste 1 (défaut).
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = resolveTableSchema('employees');
        if ($schema === null || schemaHasColumn('employees', 'family_parts')) {
            return;
        }

        Schema::table("{$schema}.employees", function (Blueprint $table): void {
            $table->decimal('family_parts', 3, 1)->default(1.0)->after('marital_status');
        });
    }

    public function down(): void
    {
        $schema = resolveTableSchema('employees');
        if ($schema === null || ! schemaHasColumn('employees', 'family_parts')) {
            return;
        }

        Schema::table("{$schema}.employees", function (Blueprint $table): void {
            $table->dropColumn('family_parts');
        });
    }
};
