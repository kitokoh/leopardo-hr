<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BIO-008 (#6773) — audit & observabilité biométrique sans fuite de données.
 *
 * `biometric_audit_logs` trace les enrôlements, révocations, vérifications,
 * rejets, bascules et changements de configuration — SANS photo, gabarit ni
 * secret (l'API du logger n'accepte que des codes et des ids). Chaque entrée
 * est rattachable à un tenant, un salarié, un site, un appareil et une
 * corrélation.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('biometric_audit_logs')) {
            Schema::create('biometric_audit_logs', function (Blueprint $table): void {
                $table->increments('id');
                $table->uuid('company_id');
                $table->unsignedInteger('employee_id')->nullable();
                $table->unsignedInteger('kiosk_id')->nullable();
                $table->unsignedInteger('site_id')->nullable();
                $table->unsignedInteger('actor_employee_id')->nullable();
                // enrollment.started|activated|revoked, verification.*,
                // fallback.used, config.changed, device.revoked...
                $table->string('event', 60);
                $table->string('method', 20)->nullable();
                $table->string('result_code', 60)->nullable();
                $table->string('correlation_id', 100)->nullable();
                $table->string('device_code_hash', 80)->nullable();
                $table->json('context')->nullable();
                $table->timestampTz('occurred_at')->useCurrent();
                $table->timestamps();

                $table->index(['company_id', 'event']);
                $table->index(['company_id', 'employee_id', 'occurred_at']);
                $table->index(['company_id', 'kiosk_id', 'occurred_at']);
                $table->index(['company_id', 'correlation_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_audit_logs');
    }
};
