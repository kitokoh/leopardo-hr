<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #7803 (PHARMA-006) — prescripteurs et ordonnances d'officine.
 *
 * `pharmacy_prescribers` : médecins/prescripteurs (n° d'inscription à
 * l'ordre). `pharmacy_prescriptions` : ordonnances (référence unique par
 * tenant, patient = PII santé, JAMAIS exposée cross-tenant).
 *
 * L'ordonnancier des produits contrôlés n'a PAS de table : il DÉRIVE du
 * journal immuable `pharmacy_stock_movements` (lecture seule).
 *
 * Gardes F-17 : schemaTableExists(), migration additive et idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('pharmacy_prescribers')) {
            Schema::create('pharmacy_prescribers', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('full_name', 191);
                $table->string('registration_number', 100)->nullable();
                $table->string('specialty', 100)->nullable();
                $table->string('phone', 50)->nullable();
                $table->string('status', 20)->default('active'); // active|archived
                $table->timestamps();

                $table->index(['company_id', 'status'], 'pharmacy_prescribers_company_status_idx');
                $table->index(['company_id', 'full_name'], 'pharmacy_prescribers_company_name_idx');
            });
        }

        if (! schemaTableExists('pharmacy_prescriptions')) {
            Schema::create('pharmacy_prescriptions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('prescriber_id');
                // PII santé — jamais exposé cross-tenant.
                $table->string('patient_name', 191);
                $table->string('patient_contact', 191)->nullable();
                $table->date('prescribed_at');
                $table->string('reference', 100);
                $table->string('notes', 500)->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'reference'], 'pharmacy_prescriptions_company_reference_unique');
                $table->index(['company_id', 'prescriber_id'], 'pharmacy_prescriptions_company_prescriber_idx');
                $table->index(['company_id', 'prescribed_at'], 'pharmacy_prescriptions_company_date_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacy_prescriptions');
        Schema::dropIfExists('pharmacy_prescribers');
    }
};
