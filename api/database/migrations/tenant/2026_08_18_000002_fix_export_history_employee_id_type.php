<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5034 — `export_history.employee_id` était typé `uuid` alors que
 * `employees.id` est un INTEGER (`increments`, migration 000101) : tout
 * INSERT historisant un export échouait en 22P02 (invalid input syntax for
 * type uuid) → l'historisation #2199 n'a jamais fonctionné en prod, et en
 * environnement de test l'erreur avalée laissait la transaction PG abortée
 * (25P02) → 500 sur GET /export/*.
 *
 * Corrige les bases ayant déjà exécuté 2026_08_15_000003 : ALTER du type de
 * colonne. La table étant vide (tous les inserts échouaient), la conversion
 * est sans risque.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('export_history')) {
            return;
        }

        $schema = resolveTableSchema('export_history');

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            // `uuid::integer` n'est pas un cast valide en PostgreSQL (42846) —
            // USING NULL : la table est vide (l'historisation #2199 échouait
            // systématiquement), aucune donnée n'est perdue.
            DB::statement(
                "ALTER TABLE \"{$schema}\".\"export_history\" ALTER COLUMN employee_id TYPE integer USING NULL"
            );
        }
        // SQLite/local : le schéma est recréé par les tests, pas de correctif.
    }

    public function down(): void
    {
        // Pas de retour arrière : le type `uuid` d'origine était un bug.
    }
};
