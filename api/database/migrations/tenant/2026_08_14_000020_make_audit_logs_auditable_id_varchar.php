<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migration Tenant 000020 — audit_logs.auditable_id → varchar(36)
 *
 * `audit_logs` est un polymorphisme `morphTo` (auditable_type/auditable_id).
 * La colonne était unsignedBigInteger : les audits des entités à clé UUID
 * (ex. `Company`, #1873 : PATCH /platform/companies/{id}/country) faisaient
 * `invalid input syntax for type bigint` (22P02) → 500 sur l'endpoint.
 * varchar(36) accepte les id entiers (paie) ET les UUID (plateforme).
 * Aligné sur resolveTableSchema() — schéma résolu via search_path (#1613).
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        $schema = resolveTableSchema('audit_logs');
        if ($schema === null || ! schemaTableExists('audit_logs')) {
            return;
        }

        DB::statement("ALTER TABLE \"{$schema}\".\"audit_logs\" ALTER COLUMN auditable_id TYPE varchar(36) USING auditable_id::varchar");
    }

    public function down(): void
    {
        $schema = resolveTableSchema('audit_logs');
        if ($schema === null || ! schemaTableExists('audit_logs')) {
            return;
        }

        DB::statement("ALTER TABLE \"{$schema}\".\"audit_logs\" ALTER COLUMN auditable_id TYPE bigint USING NULLIF(auditable_id, '')::bigint");
    }
};
