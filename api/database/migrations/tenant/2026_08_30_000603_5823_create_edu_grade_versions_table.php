<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EduManager — Issue #5823 (EDU-007).
 *
 * edu_grade_versions : journal de VERSIONNAGE des notes publiées. Chaque
 * correction d'une note publiée écrit, AVANT la mise à jour, une ligne
 * (previous_score → new_score + justification + acteur + horodatage) :
 * jamais d'écrasement silencieux, audit complet et rejouable (spec §6.3 —
 * notes immuables après publication, correction contrôlée avec
 * justification).
 *
 * PII : `reason` (justification) reste bornée à 255 caractères — zone
 * libre contrôlée, jamais de texte non borné ; exposée uniquement aux
 * gestionnaires du tenant (RBAC EduGradePolicy).
 *
 * Invariants portés par le schéma :
 *   - `company_id` uuid NON nullable + FK composite (grade_id, company_id)
 *     → edu_grades(id, company_id) : une version cross-tenant est une
 *     violation FK en base ;
 *   - `changed_at` timestampTz (traçabilité horodatée, défaut = now) ;
 *   - index tenant-first (company_id, grade_id) pour l'audit d'une note et
 *     (company_id, changed_at) pour les journaux filtrés.
 *
 * Gardes F-17 (#1593/#1613) : schemaTableExists() + noms qualifiés ;
 * migration additive et idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('edu_grade_versions')) {
            Schema::create('edu_grade_versions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('grade_id');
                $table->decimal('previous_score', 6, 2)->nullable();
                $table->decimal('new_score', 6, 2);
                $table->string('previous_status', 20)->nullable();
                $table->string('new_status', 20);
                // Justification bornée (255) — audit, jamais de zone libre non bornée.
                $table->string('reason', 255)->nullable();
                $table->unsignedInteger('changed_by');
                $table->timestampTz('changed_at')->useCurrent();
                $table->timestamps();

                $table->index(['company_id', 'grade_id'], 'edu_grade_versions_company_grade_idx');
                $table->index(['company_id', 'changed_at'], 'edu_grade_versions_company_changed_idx');

                // Cross-tenant impossible : la paire (grade_id, company_id)
                // doit exister chez le MÊME tenant (edu_grades — EDU-007).
                $table->foreign(['grade_id', 'company_id'], 'edu_grade_versions_grade_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_grades')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_grade_versions');
    }
};
