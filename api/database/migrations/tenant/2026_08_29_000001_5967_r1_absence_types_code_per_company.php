<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #5967 (R1) — absence_types : l'unicité globale sur `code` devient une
 * unicité composite `(company_id, code)`.
 *
 * Contexte (découverte DEP-BC02 #5878) : la migration d'origine
 * (2026_04_01_000103) déclarait `$table->string('code', 20)->unique()` — une
 * CONTRAINTE UNIQUE GLOBALE dans le schéma partagé `shared_tenants`. Or les
 * codes de types d'absence sont STANDARD par tenant (CA, MAL, MAT, PAT, CSS,
 * INT, CHOM — SectorTemplateService::seedAbsenceTypes) et insérés via
 * `insertOrIgnore()` : après le premier tenant, tous les suivants voient
 * leurs inserts silencieusement ignorés → onboarding congés cassé.
 *
 * Fix : unicité par tenant (company_id, code). La contrainte globale est
 * supprimée, la contrainte composite est créée.
 *
 * Sécurité : si des doublons cross-tenant existent déjà en base, la création
 * de la contrainte composite échoue (violation) → migration rouge →
 * résolution manuelle documentée dans l'issue #5967 (pas de perte
 * silencieuse).
 *
 * Applicable dans le schéma shared_tenants (tables tenant).
 */
return new class extends Migration
{
    private const GLOBAL_CONSTRAINT = 'absence_types_code_unique';

    private const COMPOSITE_CONSTRAINT = 'absence_types_company_code_unique';

    public function up(): void
    {
        if (! Schema::hasTable('absence_types')) {
            return;
        }

        $this->dropConstraintIfExists(self::GLOBAL_CONSTRAINT);
        $this->addConstraintIfMissing(self::COMPOSITE_CONSTRAINT, '(company_id, code)');
    }

    public function down(): void
    {
        if (! Schema::hasTable('absence_types')) {
            return;
        }

        $this->dropConstraintIfExists(self::COMPOSITE_CONSTRAINT);
        $this->addConstraintIfMissing(self::GLOBAL_CONSTRAINT, '(code)');
    }

    private function dropConstraintIfExists(string $constraint): void
    {
        if ($this->constraintExists($constraint)) {
            DB::statement('ALTER TABLE absence_types DROP CONSTRAINT '.$constraint);
        }
    }

    private function addConstraintIfMissing(string $constraint, string $columns): void
    {
        if ($this->constraintExists($constraint)) {
            return;
        }

        DB::statement("ALTER TABLE absence_types ADD CONSTRAINT {$constraint} UNIQUE {$columns}");
    }

    private function constraintExists(string $constraint): bool
    {
        $row = DB::selectOne(
            'SELECT 1 FROM pg_constraint WHERE conname = ? AND connamespace = current_schema()::regnamespace',
            [$constraint],
        );

        return $row !== null;
    }
};
