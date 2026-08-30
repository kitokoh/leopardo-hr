<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #5967 (R1 BC-05 WORKFORCE, découverte DEP-BC02/#5878) — absence_types :
 * unicitié du code PAR TENANT au lieu de globale.
 *
 * L'index unique global `absence_types_code_unique` (migration
 * 2026_04_01_000103) rendait le schéma partagé (`shared_tenants`)
 * incompatible avec les codes STANDARD par tenant (CA, MAL, MAT, PAT, CSS,
 * INT, CHOM) : `SectorTemplateService::seedAbsenceTypes()` insère via
 * `insertOrIgnore()` → le 2e tenant et suivants voyaient leurs inserts
 * silencieusement ignorés (violation d'unicité globale avalée) → onboarding
 * congés cassé.
 *
 * Fix : drop de l'index global, création de l'index unique composite
 * `(company_id, code)` — tenant-first (MIGRATIONS_CONVENTIONS).
 *
 * Sécurité : si des doublons de code cross-tenant existaient déjà, la
 * création de l'index composite échoue (migration rouge) → résolution
 * manuelle documentée, jamais de perte silencieuse. Réentrante :
 * `schemaTableExists` + `Schema::hasIndex`, down() complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('absence_types')) {
            return;
        }

        // 1. Supprime l'index unique GLOBAL sur `code` (cause du bug).
        if (Schema::hasIndex('absence_types', 'absence_types_code_unique')) {
            Schema::table('absence_types', function (Blueprint $table): void {
                $table->dropUnique('absence_types_code_unique');
            });
        }

        // 2. Index unique composite tenant-first : (company_id, code).
        if (! Schema::hasIndex('absence_types', 'absence_types_company_code_unique')) {
            Schema::table('absence_types', function (Blueprint $table): void {
                $table->unique(['company_id', 'code'], 'absence_types_company_code_unique');
            });
        }
    }

    public function down(): void
    {
        if (! schemaTableExists('absence_types')) {
            return;
        }

        if (Schema::hasIndex('absence_types', 'absence_types_company_code_unique')) {
            Schema::table('absence_types', function (Blueprint $table): void {
                $table->dropUnique('absence_types_company_code_unique');
            });
        }

        // Restaure l'index global d'origine (état pré-fix) si absent.
        if (! Schema::hasIndex('absence_types', 'absence_types_code_unique')) {
            Schema::table('absence_types', function (Blueprint $table): void {
                $table->unique('code', 'absence_types_code_unique');
            });
        }
    }
};
