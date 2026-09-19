<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #7638 (TRAVEL-STAFF) — pont RH ↔ verticale voyage.
 *
 * Décision d'architecture (SOLUTION_TRAVEL_AGENCY.md §685) : « HR reste
 * propriétaire des employés ; la verticale référence employee_id par
 * valeur » — donc AUCUNE clé étrangère vers `employees` (table hors du
 * schéma tenant), l'appartenance au tenant est validée à l'écriture.
 *
 * Une affectation = un employé RH + un rôle métier travel
 * (driver/agent/controller/office_manager) + un scope (bureau OU trajet,
 * exactement un des deux), avec cycle de vie actif → révoqué.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_staff_assignments')) {
            Schema::create('travel_staff_assignments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                // Référence PAR VALEUR — HR propriétaire, pas de FK (§685).
                $table->unsignedBigInteger('employee_id');
                $table->string('role', 30); // driver|agent|controller|office_manager
                $table->unsignedBigInteger('office_id')->nullable();
                $table->unsignedBigInteger('trip_id')->nullable();
                $table->string('status', 20)->default('active'); // active|revoked
                $table->timestamp('revoked_at')->nullable();
                $table->unsignedBigInteger('revoked_by_user_id')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'employee_id'], 'travel_staff_company_employee_idx');
                $table->index(['company_id', 'trip_id'], 'travel_staff_company_trip_idx');
                $table->index(['company_id', 'office_id'], 'travel_staff_company_office_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_staff_assignments');
    }
};
