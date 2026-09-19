<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #7764 (spec MISSION_ESPACE_CLIENT §3.4) — compteur mensuel d'usage IA
 * PERSISTANT.
 *
 * Remplace le compteur `Cache` volatil d'`AIRateLimiter` (perdu à chaque
 * redéploiement / flush — le quota mensuel repartait de zéro). Une ligne par
 * (company, période `YYYY-MM`), incrémentée par requête IA : le quota du plan
 * (`config('ai.quotas')`, requêtes/mois) se compare à `used`.
 *
 * Choix « table dédiée » plutôt que « agrégation des lignes consumption du
 * ledger » : le quota du plan se compte en REQUÊTES alors que le ledger se
 * compte en TOKENS achetés — mélanger les deux unités dans une même table
 * rendrait le solde ambigu. L'incrément est atomique (`UPDATE ... used =
 * used + 1` après upsert), là où un `COUNT()` mensuel sur le ledger serait
 * plus coûteux et n'aurait pas la même sémantique.
 *
 * Isolation : `company_id` (uuid) + unique (`company_id`, `period`).
 * Migration réentrante (`schemaTableExists`, garde #1613), `down()` complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('ai_usage_counters')) {
            return;
        }

        Schema::create('ai_usage_counters', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id');
            $table->string('period', 7); // YYYY-MM
            $table->unsignedBigInteger('used')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'period'], 'ai_usage_counters_company_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_counters');
    }
};
