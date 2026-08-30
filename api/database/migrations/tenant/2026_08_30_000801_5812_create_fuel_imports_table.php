<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #5812 (FUEL-018) — FuelStation : trace d'audit des imports.
 *
 * `fuel_imports` : chaque import contrôlé (produits/shifts/relevés) laisse
 * une trace tenant-scoped (type, lignes, statut, auteur). Additive et
 * réentrante (garde `schemaTableExists`, règle #5431).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fuel_imports')) {
            Schema::create('fuel_imports', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('type', 30);
                $table->unsignedInteger('rows_total')->default(0);
                $table->unsignedInteger('rows_imported')->default(0);
                $table->string('status', 20)->default('completed');
                $table->unsignedBigInteger('imported_by_user_id')->nullable();
                $table->text('error_summary')->nullable();

                $table->timestamps();

                $table->index(['company_id', 'type'], 'fuel_imports_company_type_idx');
            });

            DB::statement("COMMENT ON TABLE fuel_imports IS 'Trace d audit des imports FuelStation (FUEL-018/#5812).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_imports');
    }
};
