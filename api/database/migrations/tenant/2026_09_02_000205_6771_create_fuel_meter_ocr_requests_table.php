<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI-002 (#6771) — demandes d'OCR de compteurs FuelStation (queue durable).
 *
 * `fuel_meter_ocr_requests` : chaque photo de compteur soumise par un
 * employé devient une ligne persistée AVANT le dispatch du job de queue —
 * une perte de queue ne perd jamais la demande (elle reste rejouable et
 * visible pour revue). Le job écrit les transitions de statut :
 * queued → processing → succeeded | needs_review | rejected | failed.
 *
 * Un relevé n'est auto-enregistré (reading_id) que si la confiance est au
 * dessus du seuil configuré SANS anomalie (unité, valeur décroissante) ;
 * tout doute part en revue humaine (`needs_review`) — l'OCR ne clôture
 * jamais seule une session de pompe ou de caisse.
 *
 * Convention #1613 : nouvelle table créée via un Schema::create au nom nu
 * (comme 2026_09_02_000203_6773_create_biometric_audit_logs_table.php),
 * garde d'existence via le helper global `schemaTableExists()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fuel_meter_ocr_requests')) {
            Schema::create('fuel_meter_ocr_requests', function (Blueprint $table): void {
                $table->increments('id');
                $table->uuid('company_id')->index();
                $table->unsignedInteger('station_id');
                $table->unsignedInteger('pump_id');
                $table->unsignedInteger('meter_id');
                $table->unsignedInteger('requested_by_employee_id');
                $table->unsignedInteger('shift_id')->nullable();
                $table->string('photo_path', 255);

                // queued | processing | succeeded | needs_review | rejected | failed
                $table->string('status', 20)->default('queued');

                // Résultat OCR exploitable (unités mineures entières, jamais de flottant).
                $table->unsignedBigInteger('extracted_value_minor')->nullable();
                $table->string('extracted_unit', 10)->nullable();
                $table->decimal('confidence', 5, 4)->nullable();
                // Anomalies machine stables : LOW_CONFIDENCE, UNIT_MISMATCH,
                // DECREASING_READING…
                $table->json('anomalies')->nullable();

                $table->string('correlation_id', 100);
                $table->string('model_version', 60)->nullable();
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->string('error_code', 60)->nullable();

                // Revue humaine (manager).
                $table->unsignedInteger('reviewed_by_employee_id')->nullable();
                // accepted | rejected
                $table->string('review_decision', 10)->nullable();
                $table->timestampTz('reviewed_at')->nullable();

                // fuel_meter_readings.id auto-créé lors d'un enregistrement réussi.
                $table->unsignedInteger('reading_id')->nullable();

                $table->timestamps();

                $table->index(['company_id', 'status'], 'fuel_ocr_requests_company_status_idx');
                $table->unique('correlation_id', 'fuel_ocr_requests_correlation_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_meter_ocr_requests');
    }
};
