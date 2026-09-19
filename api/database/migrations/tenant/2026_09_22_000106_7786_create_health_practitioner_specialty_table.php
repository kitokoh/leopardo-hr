<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HealthManager — Issue #7786 (HC-002, BC-30).
 *
 * health_practitioner_specialty : affectation n-n praticien ↔ spécialité,
 * tenant-scoped. FK composites des DEUX côtés : une affectation
 * cross-tenant est une violation FK en base (pattern edu_teacher_subjects).
 * UNIQUE(company_id, practitioner_id, specialty_id) : idempotence du sync.
 *
 * Gardes F-17 (#1593/#1613) : schemaTableExists() + noms qualifiés ;
 * migration additive et idempotente. Une table = une migration (#7452).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('health_practitioner_specialty')) {
            Schema::create('health_practitioner_specialty', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('practitioner_id');
                $table->unsignedBigInteger('specialty_id');
                $table->timestamps();

                $table->unique(['company_id', 'practitioner_id', 'specialty_id'], 'health_prac_spec_company_prac_spec_unique');
                $table->index(['company_id', 'practitioner_id'], 'health_prac_spec_company_prac_idx');
                $table->index(['company_id', 'specialty_id'], 'health_prac_spec_company_spec_idx');

                // Cross-tenant impossible : chaque paire (X_id, company_id)
                // doit exister chez le MÊME tenant.
                $table->foreign(['practitioner_id', 'company_id'], 'health_prac_spec_practitioner_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('health_practitioners')
                    ->cascadeOnDelete();
                $table->foreign(['specialty_id', 'company_id'], 'health_prac_spec_specialty_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('health_specialties')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('health_practitioner_specialty');
    }
};
