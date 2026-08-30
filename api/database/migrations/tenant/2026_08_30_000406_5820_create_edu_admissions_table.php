<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5820 (EDU-004) — dossiers d'admission et lien CRM client.
 *
 * `edu_admissions` : dossier d'inscription d'un futur élève. Pipeline borné
 * par CHECK (new|document_pending|review|accepted|waitlisted|rejected|
 * cancelled|converted) ; `external_id` unique PAR TENANT → rejeu idempotent ;
 * doublons détectés par (company_id, external_id) et par numéro de dossier.
 *
 * Lien CRM client NON couplant : `crm_contact_id` nullable SANS FK — simple
 * référence de contrat remplie par l'adaptateur CRM (le CRM commercial
 * plateforme reste inaccessible depuis ce module, spec §2).
 *
 * Consentement : `consent_contact` (booléen, RGPD) + `consented_at` ;
 * aucun traitement marketing sans consentement explicite.
 *
 * FK composites anti cross-tenant vers edu_students / edu_academic_years /
 * edu_campuses. company_id uuid NON nullable, index tenant-first, gardes
 * schemaTableExists, CHECK gardé pg_constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('edu_admissions')) {
            Schema::create('edu_admissions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('student_id')->nullable()->index();
                $table->unsignedBigInteger('academic_year_id')->index();
                $table->unsignedBigInteger('campus_id')->nullable()->index();
                $table->string('admission_number', 50);
                $table->string('applicant_first_name', 100);
                $table->string('applicant_last_name', 100);
                $table->string('applicant_email', 150)->nullable();
                $table->string('applicant_phone', 30)->nullable();
                $table->date('applicant_birth_date')->nullable();
                $table->string('status', 30)->default('new');
                $table->string('source', 50)->nullable();
                $table->string('external_id', 100)->nullable();
                $table->string('crm_contact_id', 64)->nullable();
                $table->boolean('consent_contact')->default(false);
                $table->timestamp('consented_at')->nullable();
                $table->date('applied_at');
                $table->timestamp('converted_at')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'admission_number'], 'edu_admissions_company_number_unique');
                $table->unique(['company_id', 'external_id'], 'edu_admissions_company_external_unique');
                $table->index(['company_id', 'status'], 'edu_admissions_company_status_idx');
                $table->index(['company_id', 'applied_at'], 'edu_admissions_company_applied_idx');

                $table->foreign(['student_id', 'company_id'], 'edu_admissions_student_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_students')
                    ->nullOnDelete();
                $table->foreign(['academic_year_id', 'company_id'], 'edu_admissions_year_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_academic_years')
                    ->cascadeOnDelete();
                $table->foreign(['campus_id', 'company_id'], 'edu_admissions_campus_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_campuses')
                    ->nullOnDelete();
            });

            $schema = resolveTableSchema('edu_admissions');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_admissions_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_admissions\" ADD CONSTRAINT edu_admissions_status_check "
                    ."CHECK (status IN ('new','document_pending','review','accepted','waitlisted','rejected','cancelled','converted')); "
                    ."END IF; END $$"
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_admissions');
    }
};
