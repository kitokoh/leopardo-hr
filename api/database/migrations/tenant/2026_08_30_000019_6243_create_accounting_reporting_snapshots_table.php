<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module Comptabilité / BC-22 — issue #6243 (D10).
 *
 * Snapshots horodatés des read models de reporting (fraîcheur) :
 *   - table TENANT additive (garde schemaTableExists) ;
 *   - clé unique `(company_id, report, period_from, period_to)` : un seul
 *     snapshot par (tenant, read model, période) — le recompute remplace la
 *     ligne (idempotent) et incrémente `version` uniquement si le contenu
 *     change ;
 *   - `refreshed_at` = horodatage de la dernière exécution (fraîcheur exposée
 *     à l'API) ;
 *   - `payload` JSONB = agrégats du read model (déterministe, aucune donnée
 *     brute nominative — pas de PII inutile, exigence BC-22).
 *
 * Règles : migration additive et idempotente ; `company_id` uuid NON nullable
 * (isolation tenant via BelongsToCompany, garde fail-closed #3727) ; aucun
 * index global — uniquement l'index composite tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('accounting_reporting_snapshots')) {
            Schema::create('accounting_reporting_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->string('report', 60);
                $table->date('period_from');
                $table->date('period_to');
                $table->unsignedInteger('version')->default(1);
                $table->jsonb('payload');
                $table->timestamp('refreshed_at');
                $table->timestamps();

                $table->unique(
                    ['company_id', 'report', 'period_from', 'period_to'],
                    'acc_reporting_snapshots_company_report_period_unique',
                );
            });
        }
    }

    public function down(): void
    {
        if (schemaTableExists('accounting_reporting_snapshots')) {
            Schema::dropIfExists('accounting_reporting_snapshots');
        }
    }
};
