<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HospitalityManager — HOSP-003 (#7945, BC-32) : équipe par établissement.
 *
 * `hospitality_property_staff` : pivot staff ↔ établissement (RH reste
 * propriétaire des employés, référence PAR VALEUR comme
 * `restaurant_branch_staff` #7909 / `travel_staff_assignments`) :
 * - `property_id` : établissement (hospitality_properties) du MÊME tenant ;
 * - `employee_id` : employé RH du MÊME tenant (contrôle cross-tenant strict
 *   dans HospitalityPropertyStaffService::assign(), 422 sinon) ;
 * - `role`        : rôle MÉTIER descriptif optionnel (réceptionniste,
 *   gouvernante, gérant…) — JAMAIS une source d'autorisation (spec §4 :
 *   l'autorisation passe par EmployeeResourceAssignment, pas par ce libellé) ;
 * - `assigned_at` : date d'affectation ;
 * - softDeletes   : le retrait est un soft delete (historique conservé) ;
 * - unicité (company_id, property_id, employee_id) : un employé n'est
 *   affecté qu'une fois par établissement (409 au niveau API sur doublon
 *   actif ; la ré-affectation restaure la ligne soft-deleted).
 *
 * Table tenant-scoped (`company_id` uuid non nullable, tenant-first), sans
 * FK (pattern Travel moderne) : colonnes simples + index nommés. Migration
 * idempotente (garde `schemaTableExists`), `down()` complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('hospitality_property_staff')) {
            Schema::create('hospitality_property_staff', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('property_id');
                $table->unsignedBigInteger('employee_id');
                $table->string('role', 80)->nullable();
                $table->timestamp('assigned_at')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'property_id'], 'hospitality_property_staff_company_property_idx');
                $table->index(['company_id', 'employee_id'], 'hospitality_property_staff_company_employee_idx');
                $table->unique(['company_id', 'property_id', 'employee_id'], 'hospitality_property_staff_unique');
            });

            DB::statement("COMMENT ON TABLE hospitality_property_staff IS 'Affectations employes <-> etablissements hospitality - un employe par (tenant, etablissement), retrait en soft delete, role descriptif (HOSP-003 #7945).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hospitality_property_staff');
    }
};
