<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #2199 — GET /export/history était un stub (`data => []` en dur).
 *
 * Table d'historique des exports du portail manager (append-only) : chaque
 * export (employés, présences, bulletins, absences, formations, contrats,
 * véhicules, journal/grand-livre/OD comptables) enregistre une ligne
 * tenant-scopée (company_id) avec l'acteur, le type, le format, le nombre
 * d'enregistrements et le fichier produit — pour que /export/history
 * réponde sur une source réelle, paginée et isolée par tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Schéma résolu via le search_path (convention issue #1613 / F-17).
        if (schemaTableExists('export_history')) {
            return;
        }

        Schema::create('export_history', function (Blueprint $table): void {
            $table->id();
            // Tenant : UUID du propriétaire (index (company_id, created_at)
            // couvre la requête paginée de /export/history).
            $table->uuid('company_id');
            $table->unsignedInteger('employee_id')->nullable(); // employees.id est INTEGER (increments) — le type uuid cassait l'insert (22P02, #5034)
            // Type d'export : employees | attendance | pay_slips | absences |
            // training | contracts | vehicles | payroll_journal |
            // payroll_ledger | accounting_od.
            $table->string('type', 100);
            $table->string('format', 10)->nullable(); // json | csv | xlsx
            $table->unsignedInteger('record_count')->nullable();
            $table->string('filename', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        $schema = resolveTableSchema('export_history');

        if ($schema !== null) {
            Schema::dropIfExists("{$schema}.export_history");
        }
    }
};
