<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5812 (FUEL-018) — imports sécurisés (journal d'audit).
 *
 * `fuel_imports` : journal des imports CSV (relevés de compteur, entrées de
 * stock, produits) — statut, compteurs, résumé d'erreurs, fichier d'origine
 * (nom assaini, jamais de chemin sensible), exécuté par qui. Les exports
 * réutilisent `export_history` (pattern HR, audit via DataAccessAuditLogger).
 *
 * Toute importation est asynchrone (job tenant-scoped, idempotent) et
 * rejouable : le statut + les compteurs permettent la reprise sans doublon.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fuel_imports')) {
            Schema::create('fuel_imports', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('kind', 40); // meter_readings|stock_entries|products
                $table->string('file_name', 200);
                $table->string('status', 20)->default('uploaded'); // uploaded|processing|completed|failed
                $table->unsignedInteger('total_rows')->default(0);
                $table->unsignedInteger('processed_rows')->default(0);
                $table->unsignedInteger('failed_rows')->default(0);
                $table->jsonb('error_summary')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestampTz('started_at')->nullable();
                $table->timestampTz('finished_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'kind', 'status'], 'fuel_imports_kind_status_idx');
            });

            DB::statement("COMMENT ON TABLE fuel_imports IS 'Journal des imports FuelStation (CSV validé, asynchrone, rejouable) — FUEL-018 (#5812).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_imports');
    }
};
