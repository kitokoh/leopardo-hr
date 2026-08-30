<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5821 (EDU-005) — présence scolaire.
 *
 * `edu_attendance_records` : présence d'un élève par (classe, séance,
 * matière, date). `student_id` FK COMPOSITE vers `edu_students` GARDÉE
 * (table livrée par EDU-002, PR #5974) — même pattern que FUEL (FK sautée
 * si table absente, validation applicative).
 *
 * Corrections VERSIONNÉES : la ligne courante porte `version` et les
 * métadonnées de la dernière correction ; chaque correction est AUDITÉE en
 * append-only dans `edu_attendance_corrections` (from/to, motif, auteur,
 * horodatage) — aucune correction silencieuse, rejeu impossible.
 *
 * UNIQUE (company_id, student_id, class_id, session_date, subject_id) :
 * une présence par élève/séance — l'enregistrement est idempotent par
 * nature (rejeu → mise à jour de la même ligne, jamais de doublon).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('edu_attendance_records')) {
            Schema::create('edu_attendance_records', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('class_id')->index();
                $table->unsignedBigInteger('student_id')->index();
                $table->unsignedBigInteger('subject_id')->nullable()->index();
                $table->date('session_date');
                $table->string('session_label', 120)->nullable(); // ex. "Matin", "Cours 1"
                $table->string('status', 20)->default('present'); // present|absent|late|excused
                $table->string('reason', 255)->nullable(); // motif (requis si absent)
                $table->boolean('justified')->default(false);
                $table->unsignedInteger('recorded_by');
                $table->unsignedInteger('version')->default(1);
                $table->string('previous_status', 20)->nullable();
                $table->string('correction_reason', 255)->nullable();
                $table->unsignedInteger('corrected_by')->nullable();
                $table->timestampTz('corrected_at')->nullable();
                $table->timestamps();

                $table->unique(
                    ['company_id', 'student_id', 'class_id', 'session_date', 'subject_id'],
                    'edu_attendance_record_unique'
                );
                $table->index(['company_id', 'class_id', 'session_date'], 'edu_attendance_class_date_idx');
            });

            $this->addCheck('edu_attendance_records', 'edu_attendance_status_check', "status IN ('present', 'absent', 'late', 'excused')");
        }

        if (! schemaTableExists('edu_attendance_corrections')) {
            Schema::create('edu_attendance_corrections', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('record_id')->index();
                $table->string('from_status', 20);
                $table->string('to_status', 20);
                $table->string('reason', 255);
                $table->unsignedInteger('corrected_by');
                $table->timestampTz('corrected_at')->useCurrent();
            });

            DB::statement("COMMENT ON TABLE edu_attendance_corrections IS 'Audit append-only des corrections de présence (EDU-005, #5821) — aucune correction silencieuse.'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_attendance_corrections');
        Schema::dropIfExists('edu_attendance_records');
    }

    private function addCheck(string $table, string $name, string $expression): void
    {
        $schema = resolveTableSchema($table);

        if ($schema === null) {
            return;
        }

        $exists = DB::selectOne('SELECT 1 FROM pg_constraint WHERE conname = ?', [$name]);

        if ($exists === null) {
            DB::statement("ALTER TABLE {$schema}.{$table} ADD CONSTRAINT {$name} CHECK ({$expression})");
        }
    }
};
