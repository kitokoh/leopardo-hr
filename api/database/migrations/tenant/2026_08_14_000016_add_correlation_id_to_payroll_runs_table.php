<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #1874 — identifiant de corrélation sur les runs de paie.
 *
 * Chaque `payroll_runs` reçoit un `correlation_id` UUID (additif,
 * idempotent — garde `schemaHasColumn` F-17). Le remplissage se fait dans
 * `PayrollCalculator::calculateRun()` (génération à la première passe) ;
 * les runs historiques restent NULL tant qu'ils ne sont pas recalculés.
 * L'index est UNIQUE : chaque run possède son propre identifiant de
 * corrélation (PostgreSQL autorise plusieurs NULL dans un index unique).
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = resolveTableSchema('payroll_runs');
        if ($schema === null || schemaHasColumn('payroll_runs', 'correlation_id')) {
            return;
        }

        Schema::table("{$schema}.payroll_runs", function (Blueprint $table): void {
            $table->uuid('correlation_id')->nullable()->unique()->after('country_code');
        });
    }

    public function down(): void
    {
        $schema = resolveTableSchema('payroll_runs');
        if ($schema !== null && schemaHasColumn('payroll_runs', 'correlation_id')) {
            Schema::table("{$schema}.payroll_runs", function (Blueprint $table): void {
                $table->dropUnique(['correlation_id']);
                $table->dropColumn('correlation_id');
            });
        }
    }
};
