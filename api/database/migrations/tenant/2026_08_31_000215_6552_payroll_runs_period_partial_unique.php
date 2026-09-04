<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Issue #6552 — `payroll_runs` n'a pas de garde anti-doublon de période :
 * l'index existant (company_id, period_start, period_end) n'est pas unique,
 * donc un double-clic/concurrence peut créer deux runs draft sur la même
 * période → double paie potentielle.
 *
 * Correctif : index unique PARTIEL `payroll_runs_company_period_partial_unique`
 * sur (company_id, period_start, period_end) pour les runs NON cancelled
 * (un run annulé libère la période pour une recréation). La violation est
 * gérée côté contrôleur (409 PAYROLL_RUN_PERIOD_CONFLICT, SQLSTATE 23505).
 *
 * Postgres uniquement (syntaxe `WHERE` d'index partiel) — no-op sur les
 * autres drivers.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! schemaTableExists('payroll_runs')) {
            return;
        }

        // Fail loud si des doublons (company_id, pays, période) non-cancelled
        // existent déjà : on ne supprime jamais de lignes silencieusement.
        // Le pays fait partie de la clé : une société multi-pays peut exécuter
        // une paie CI et une paie SN sur la même période (StorePayrollRunRequest
        // accepte un country_code explicite), mais jamais deux fois le même.
        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS payroll_runs_company_period_partial_unique
            ON payroll_runs (company_id, period_start, period_end, country_code)
            WHERE status <> \'cancelled\''
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS payroll_runs_company_period_partial_unique CASCADE');
    }
};
