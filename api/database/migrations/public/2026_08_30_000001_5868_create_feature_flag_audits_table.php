<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #5868 — Piste d'audit des bascules de feature flags (MAT-010, BC-01 PLATFORM).
 *
 * Table plateforme (schéma public) : chaque changement d'activation d'un
 * module/solution pour un tenant est horodaté, sourcé et attribué — exigence
 * « l'état est fail-closed et audité » de MAT-010.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('feature_flag_audits')) {
            Schema::create('feature_flag_audits', function (Blueprint $table) {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('flag_key', 80);
                $table->boolean('previous_value');
                $table->boolean('new_value');
                $table->string('source', 40)->default('platform_controller');
                $table->unsignedBigInteger('actor_user_id')->nullable();
                $table->timestampTz('created_at')->useCurrent();

                $table->index(['company_id', 'flag_key', 'created_at'], 'feature_flag_audits_company_key_idx');
            });

            DB::statement("COMMENT ON TABLE feature_flag_audits IS 'Audit des bascules de feature flags par tenant (MAT-010 #5868).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_flag_audits');
    }
};
