<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5324 — workflow de départ : enregistrement formel du départ employé.
 *
 * Le statut `departed` (migration 000001) + ce dossier = l'empreinte HR du
 * départ. Le solde de tout compte et l'attestation restent des endpoints
 * Payroll (EndOfContractController) — HR orchestre, Payroll calcule
 * (constitution §III). L'exclusion des runs de paie est le gap G6 (module
 * Payroll, hors périmètre HR).
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (! schemaTableExists('employee_departures')) {
            Schema::create('employee_departures', function (Blueprint $table) {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedInteger('employee_id');
                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
                $table->enum('departure_type', ['resignation', 'termination', 'end_of_contract', 'retirement']);
                $table->string('reason', 500)->nullable();
                $table->date('last_work_day');
                $table->boolean('notice_served')->default(false);
                $table->unsignedSmallInteger('notice_days_served')->nullable();
                $table->date('departed_at');
                $table->unsignedInteger('created_by')->nullable();
                $table->foreign('created_by')->references('id')->on('employees')->nullOnDelete();
                $table->timestampsTz();

                $table->index(['company_id', 'employee_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_departures');
    }
};
