<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MAT-010 (#5868) — Feature flags et kill switch (BC-01 PLATFORM).
 *
 * Table publique (schema public, non tenant) portant les interrupteurs
 * de feature flags plateforme : kill switch global, par module, par tenant,
 * par solution, par provider et par version. L'état par défaut est
 * `enabled = false` (fail-closed) : une feature inconnue ou coupée est
 * refusée. Chaque ligne garde un historique append-only (qui, quand, avant,
 * après, raison) pour l'audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_feature_flags', function (Blueprint $table): void {
            $table->id();
            $table->string('flag_key', 120);
            $table->string('dimension', 20)->default('global');
            $table->string('dimension_value', 120)->nullable();
            // Fail-closed : toute entrée nouvelle est désactivée par défaut.
            $table->boolean('enabled')->default(false);
            $table->text('reason')->nullable();
            $table->string('changed_by', 190)->nullable();
            $table->jsonb('history')->default('[]');
            $table->timestamps();

            // Une seule entrée par couple (flag, dimension, valeur).
            $table->unique(['flag_key', 'dimension', 'dimension_value'], 'platform_feature_flags_key_dim_value_unique');
            // Le kill switch global d'un flag est unique (dimension_value NULL).
            $table->unique(['flag_key', 'dimension'], 'platform_feature_flags_key_dim_global_unique')
                ->whereNull('dimension_value');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_feature_flags');
    }
};
