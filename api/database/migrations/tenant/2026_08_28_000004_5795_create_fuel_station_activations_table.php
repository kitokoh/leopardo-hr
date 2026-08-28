<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #5795 — Activations FuelStation par tenant.
 *
 * `fuel_station_activations` : activation idempotente de la solution sur un
 * tenant (upsert par company_id), avec version de manifest et audit.
 *
 * Conventions : uuid PK, `company_id` non nullable, timestamps, garde
 * schemaTableExists() (#1613).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fuel_station_activations')) {
            Schema::create('fuel_station_activations', function (Blueprint $table): void {
                $table->uuid('company_id')->primary();
                $table->string('manifest_version', 30);
                $table->string('status', 20)->default('active');
                $table->timestamp('activated_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_station_activations');
    }
};
