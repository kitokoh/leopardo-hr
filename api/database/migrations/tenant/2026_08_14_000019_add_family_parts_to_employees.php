<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CI (#2117) — parts fiscales / charges de famille pour la RICF
 * (réduction d'impôt pour charges de famille, CGI CI art. 120).
 *
 * Migration additive sur `employees` :
 *   - `family_parts` : nombre de parts fiscales (décimal, demi-points) —
 *     null = défaut moteur 1 part (célibataire sans enfant, aucune
 *     réduction). Exemples : marié sans enfant = 2 ; marié 1 enfant = 2,5 ;
 *     +0,5 part par enfant à charge, plafonné à 5 (art. 120 CGI CI).
 *     La situation de famille à retenir est celle au 1er janvier de
 *     l'année d'acquisition du revenu (art. 120 al. 2).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('employees')) {
            return;
        }

        if (! schemaHasColumn('employees', 'family_parts')) {
            Schema::table('employees', function (Blueprint $blueprint): void {
                $blueprint->decimal('family_parts', 3, 1)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (! schemaTableExists('employees')) {
            return;
        }

        if (schemaHasColumn('employees', 'family_parts')) {
            Schema::table('employees', function (Blueprint $blueprint): void {
                $blueprint->dropColumn('family_parts');
            });
        }
    }
};
