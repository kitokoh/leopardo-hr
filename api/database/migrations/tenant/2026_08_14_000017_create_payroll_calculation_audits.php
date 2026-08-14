<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #1874 — audit & observabilité de chaque calcul de paie.
 *
 * Une ligne par simulation et par run de paie : tenant, acteur, pays,
 * version/période des règles, entrées NON sensibles (brut), résultats
 * agrégés (net, coût, impôt), identifiant de corrélation (uuid), statut et
 * classe d'erreur éventuelle. Jamais de secrets ni de données biométriques
 * brutes (la table ne porte que des colonnes whitelistées — par
 * construction, aucun token/mot de passe/biométrie ne peut y atterrir).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payroll_calculation_audits')) {
            return;
        }

        Schema::create('payroll_calculation_audits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_role', 40)->nullable();
            $table->string('country_code', 2);
            $table->string('rules_version', 40)->nullable();
            $table->date('rules_period')->nullable();
            $table->uuid('correlation_id')->index();
            $table->decimal('input_gross', 14, 2);
            $table->decimal('result_net', 14, 2)->nullable();
            $table->decimal('result_total_cost', 14, 2)->nullable();
            $table->decimal('result_income_tax', 14, 2)->nullable();
            $table->string('status', 20)->default('ok');
            $table->string('error_class', 120)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        if (! schemaTableExists('payroll_calculation_audits')) {
            return;
        }

        Schema::dropIfExists('payroll_calculation_audits');
    }
};
