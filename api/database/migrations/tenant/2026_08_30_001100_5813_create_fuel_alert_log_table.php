<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5813 (FUEL-019) — journal de déduplication des alertes FuelStation.
 *
 * `fuel_alert_log` : chaque alerte NOTIFIÉE est enregistrée avec une clé
 * d'unicité par tenant (type + cible + date) — un rejeu du job d'alertes ne
 * re-notifie jamais deux fois la même anomalie le même jour. L'audit des
 * notifications reste dans `communication_events` (module Notification) ;
 * ce journal porte la DÉDUPLICATION métier des alertes FuelStation.
 *
 * `company_id` non nullable ; index (company_id, alert_type, alert_key)
 * unique ; canaux désactivables via les préférences de notification du
 * module Notification (catégorie `fuel`).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fuel_alert_log')) {
            Schema::create('fuel_alert_log', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                // meter_anomaly | missing_closure | stock_variance | maintenance_due
                $table->string('alert_type', 40);
                $table->string('alert_key', 255);
                $table->unsignedInteger('station_id')->nullable()->index();
                $table->text('payload')->nullable();
                $table->unsignedInteger('notified_by')->nullable();
                $table->timestampTz('notified_at')->useCurrent();
                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();

                // Dédup : une alerte (type, clé) par tenant, une seule fois.
                $table->unique(['company_id', 'alert_type', 'alert_key'], 'fuel_alert_log_dedup_unique');
                $table->index(['company_id', 'notified_at'], 'fuel_alert_log_company_notified_idx');
            });

            DB::statement("COMMENT ON TABLE fuel_alert_log IS 'Journal de déduplication des alertes FuelStation (une alerte par type+clé+tenant) — FUEL-019 (#5813).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_alert_log');
    }
};
