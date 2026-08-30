<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TRAVEL-505 (#6075) — Assets d'export CSV des rapports travel.
 * Statut pending → generated | failed, chemin disque local privé (hors
 * webroot, URL signée éphémère à la lecture), idempotence par (tenant, clé).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_export_assets')) {
            Schema::create('travel_export_assets', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('report_type', 30);
                $table->string('idempotency_key', 100);
                $table->string('status', 20)->default('pending');
                $table->timestamp('from_at')->nullable();
                $table->timestamp('to_at')->nullable();
                $table->string('file_path', 255)->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->string('error_redacted', 500)->nullable();
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'idempotency_key'], 'travel_export_assets_company_key_unique');
            });

            DB::statement("COMMENT ON TABLE travel_export_assets IS 'Exports CSV des rapports travel - idempotents par (tenant, idempotency_key) (TRAVEL-505/#6075).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_export_assets');
    }
};
