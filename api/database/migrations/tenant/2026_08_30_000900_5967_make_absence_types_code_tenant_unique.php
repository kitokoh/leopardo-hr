<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #5967 (BC-05 WORKFORCE) — absence_types.code : unicité par tenant.
 *
 * L'index `absence_types_code_unique` (sur `code` SEUL) casse le seed des
 * codes standard pour les 2e+ tenants (schéma partagé `shared_tenants`) :
 * `SectorTemplateService::seedAbsenceTypes()` utilise `insertOrIgnore()`
 * et avale la violation d'unicité globale → onboarding congés vide.
 *
 * Fix : drop de l'index global, création de l'index unique composite
 * `(company_id, code)` — un code standard peut exister dans chaque tenant,
 * mais jamais deux fois dans le même tenant.
 *
 * Additive, idempotente (règle #5431) : gardes `schemaTableExists` +
 * `hasIndex` ; si des doublons cross-tenant existaient en prod, la création
 * de l'index composite échouerait (rouge explicite, résolution documentée —
 * pas de perte silencieuse).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('absence_types')) {
            return;
        }

        $table = 'absence_types';

        if (Schema::hasIndex($table, 'absence_types_code_unique')) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropUnique('absence_types_code_unique');
            });
        }

        if (! Schema::hasIndex($table, 'absence_types_company_code_unique')) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->unique(['company_id', 'code'], 'absence_types_company_code_unique');
            });
        }

        DB::statement("COMMENT ON INDEX absence_types_company_code_unique IS 'Unicite du code d absence type par tenant (BC-05/#5967).';");
    }

    public function down(): void
    {
        if (! schemaTableExists('absence_types')) {
            return;
        }

        $table = 'absence_types';

        if (Schema::hasIndex($table, 'absence_types_company_code_unique')) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropUnique('absence_types_company_code_unique');
            });
        }

        if (! Schema::hasIndex($table, 'absence_types_code_unique')) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->unique('code', 'absence_types_code_unique');
            });
        }
    }
};
