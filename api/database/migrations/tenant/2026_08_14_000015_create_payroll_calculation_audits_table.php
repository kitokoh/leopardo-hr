<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #1874 — audit et observabilité de chaque calcul de paie.
 *
 * Table d'audit IMMUABLE des calculs de paie : un enregistrement par
 * corrélation (run de paie ou simulation). Ne contient JAMAIS de données
 * individuelles (salaires d'employés, tokens, mots de passe, biométrie) :
 * uniquement des paramètres agrégés (`input_snapshot`) et des résultats
 * agrégés (`result_snapshot`), le contexte de règles (pays, version,
 * identifiant) et le statut de résolution — de quoi expliquer, reproduire
 * et auditer un résultat sans exposer les bulletins.
 *
 * Statuts (`status`) : success | validation_error | rule_missing |
 * provider_error | fallback_forbidden — voir docs/payroll/AUDIT.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Schéma résolu via le search_path (convention issue #1613 / F-17).
        if (schemaTableExists('payroll_calculation_audits')) {
            return;
        }

        Schema::create('payroll_calculation_audits', function (Blueprint $table): void {
            $table->id();
            // UUID de corrélation : joint les logs, la réponse API et l'audit.
            $table->uuid('correlation_id')->unique();
            // Tenant : NULL pour les calculs plateforme (simulation super-admin
            // sur règles nationales) ; sinon UUID du tenant propriétaire.
            // (l'index (company_id, created_at) couvre les requêtes par tenant)
            $table->uuid('company_id')->nullable();
            // Acteur : 'user' (employé manager ou super-admin) ou 'job'
            // (calcul asynchrone ProcessPayrollBatchJob / commande).
            $table->string('actor_type', 20)->default('user');
            $table->unsignedBigInteger('actor_id')->nullable();
            // Contexte de règles (MULTI-PAYS #1868/#1871) — conservé pour
            // reproduire le calcul à l'identique.
            $table->string('country_code', 2);
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('rules_version', 32)->nullable();
            $table->string('rules_identifier', 150)->nullable();
            // Paramètres d'entrée agrégés NON sensibles (ex. nombre d'employés,
            // masse salariale brute) — jamais de salaires individuels.
            $table->jsonb('input_snapshot')->nullable();
            // Résultat agrégé (ex. net total, coût employeur total).
            $table->jsonb('result_snapshot')->nullable();
            // success | validation_error | rule_missing | provider_error |
            // fallback_forbidden — observabilité/alerting des erreurs.
            $table->string('status', 32);
            $table->text('error_message')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        $schema = resolveTableSchema('payroll_calculation_audits');
        if ($schema !== null) {
            Schema::dropIfExists("{$schema}.payroll_calculation_audits");
        }
    }
};
