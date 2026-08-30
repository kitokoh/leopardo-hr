<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EduManager — Issue #5821 (EDU-005).
 *
 * edu_attendance_records : présence scolaire par classe/séance (tenant).
 * Un enregistrement = (classe, élève, date) ; statut borné (CHECK) ; les
 * corrections sont VERSIONNÉES dans edu_attendance_corrections (jamais
 * d'écrasement silencieux) ; les exports sont filtrés par tenant via les
 * indexes tenant-first.
 *
 * PII (classification `docs/architecture/EDUMANAGER_DONNEES.md`) :
 *   - `note` : zone libre pouvant contenir des informations personnelles
 *     (santé, motif libre...) — JAMAIS exposée hors tenant (RBAC) ;
 *   - `reason_code` : motif codifié (sick, family, other...) — vocabulaire
 *     borné, pas de texte libre.
 *
 * Invariants portés par le schéma :
 *   - `company_id` uuid NON nullable + UNIQUE(id, company_id) : clé
 *     d'intégrité des FK composites de la table de corrections ;
 *   - FK composite (student_id, company_id) → edu_students(id, company_id) :
 *     un élève d'un autre tenant est une violation FK en base ;
 *   - FK composite (class_id, company_id) → edu_classes(id, company_id),
 *     ajoutée CONDITIONNELLEMENT : edu_classes est livrée par EDU-003
 *     (#5819) — tant qu'elle n'existe pas, la contrainte est omise
 *     (migration additive et idempotente, gardes F-17 #1593/#1613) ;
 *   - UNIQUE(company_id, class_id, student_id, attendance_date) : un seul
 *     enregistrement par élève/classe/jour (idempotence) ;
 *   - CHECK `status` (present|absent|late|excused) — valeurs inconnues
 *     rejetées ;
 *   - indexes tenant-first pour listes, dashboards et exports filtrés.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('edu_attendance_records')) {
            Schema::create('edu_attendance_records', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('class_id');
                $table->unsignedBigInteger('student_id');
                $table->date('attendance_date');
                // present | absent | late | excused — CHECK edu_attendance_records_status_check
                $table->string('status', 20);
                // Motif codifié (sick, family, other…) — vocabulaire borné.
                $table->string('reason_code', 30)->nullable();
                // PII potentielle (motif libre, santé…) — jamais hors tenant.
                $table->text('note')->nullable();
                $table->unsignedInteger('recorded_by')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'class_id', 'student_id', 'attendance_date'], 'edu_attendance_records_company_class_student_date_unique');
                // Clé d'intégrité des FK composites de edu_attendance_corrections.
                $table->unique(['id', 'company_id'], 'edu_attendance_records_id_company_unique');
                $table->index(['company_id', 'attendance_date'], 'edu_attendance_records_company_date_idx');
                $table->index(['company_id', 'class_id', 'attendance_date'], 'edu_attendance_records_company_class_date_idx');
                $table->index(['company_id', 'student_id'], 'edu_attendance_records_company_student_idx');

                // Cross-tenant impossible : la paire (student_id, company_id)
                // doit exister chez le MÊME tenant (edu_students — EDU-002).
                $table->foreign(['student_id', 'company_id'], 'edu_attendance_records_student_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_students')
                    ->cascadeOnDelete();
            });

            $schema = resolveTableSchema('edu_attendance_records');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_attendance_records_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_attendance_records\" ADD CONSTRAINT edu_attendance_records_status_check "
                    ."CHECK (status IN ('present','absent','late','excused')); END IF; END $$"
                );

                // FK composite vers edu_classes — créée par EDU-003 (#5819) ;
                // garde d'existence : tant que la table n'existe pas (lot
                // EDU-003 pas encore livré), la contrainte est omise plutôt
                // que de faire échouer la migration (additive, F-17).
                // Prérequis EDU-003 : edu_classes porte UNIQUE(id, company_id).
                if (schemaTableExists('edu_classes')) {
                    DB::statement(
                        "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_attendance_records_class_company_fk') "
                        ."THEN ALTER TABLE \"{$schema}\".\"edu_attendance_records\" ADD CONSTRAINT edu_attendance_records_class_company_fk "
                        ."FOREIGN KEY (class_id, company_id) REFERENCES \"{$schema}\".\"edu_classes\" (id, company_id); END IF; END $$"
                    );
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_attendance_records');
    }
};
