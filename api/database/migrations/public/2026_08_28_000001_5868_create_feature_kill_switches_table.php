<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MAT-010 (#5868) — Feature kill switches (plateforme).
 *
 * Interrupteur global par feature/module : une ligne active = le module est
 * STOPPÉ pour toute la plateforme (fail-closed dans `Company::hasFeature`),
 * sans suppression de données. L'activation/désactivation est idempotente,
 * horodatée et tracée (`toggled_by` / `toggled_at` / `reason`).
 *
 * Schéma `public` (plateforme) : l'interrupteur est global, géré par le
 * super-admin — pas de scopage tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        // #1933/#1613 : garde idempotente (même pattern que les migrations
        // public existantes) — le runner est rejoué par les déploiements.
        if (Schema::hasTable('feature_kill_switches')) {
            return;
        }

        Schema::create('feature_kill_switches', function (Blueprint $table): void {
            $table->id();
            $table->string('feature_key', 64)->unique();
            $table->boolean('is_active')->default(false);
            $table->string('reason', 500)->nullable();
            $table->string('toggled_by', 191)->nullable();
            $table->timestampTz('toggled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_kill_switches');
    }
};
