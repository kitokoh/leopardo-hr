<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5812 (FUEL-018) — import/export sécurisé.
 *
 * `fuel_imports` : journal des imports CSV (products|equipment|shifts|
 * readings) avec validation ligne à ligne, limites (taille/lignes),
 * preview (dry-run), rollback logique (aucune écriture si une ligne est
 * invalide) et audit (imported_by, erreurs ligne par ligne).
 *
 * Tenant-scoped (company_id uuid indexé) ; aucune donnée d'un autre tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fuel_imports')) {
            Schema::create('fuel_imports', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('import_type', 20); // products|equipment|shifts|readings
                $table->string('filename', 200);
                $table->string('status', 16)->default('pending'); // pending|validated|completed|failed
                $table->unsignedInteger('total_lines')->default(0);
                $table->unsignedInteger('valid_lines')->default(0);
                $table->unsignedInteger('error_lines')->default(0);
                $table->jsonb('errors')->nullable();
                $table->unsignedInteger('imported_by')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'created_at'], 'fuel_imports_company_created_idx');
            });
        }

        $this->addChecks();
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_imports');
    }

    private function constraintExists(string $name): bool
    {
        $row = DB::selectOne('SELECT 1 FROM pg_constraint WHERE conname = ?', [$name]);

        return $row !== null;
    }

    private function addChecks(): void
    {
        $schema = resolveTableSchema('fuel_imports');

        if ($schema === null) {
            return;
        }

        $constraints = [
            'fuel_imports_type_check' => "import_type IN ('products', 'equipment', 'shifts', 'readings')",
            'fuel_imports_status_check' => "status IN ('pending', 'validated', 'completed', 'failed')",
        ];

        foreach ($constraints as $name => $check) {
            if ($this->constraintExists($name)) {
                continue;
            }

            DB::statement("ALTER TABLE {$schema}.fuel_imports ADD CONSTRAINT {$name} CHECK ({$check})");
        }
    }
};
