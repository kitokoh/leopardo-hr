<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5811 (FUEL-017) — reporting opérationnel : read models + exports.
 *
 * - `fuel_report_snapshots` : read models horodatés (volumes par pompe,
 *   résumé ventes, état des stocks, résumé des écarts, résumé des shifts).
 *   Recalcul IDEMPOTENT : un même couple (company, station, type, date)
 *   est upserté — jamais de doublon, résultat rejouable.
 * - `fuel_report_exports` : exports asynchrones (CSV) pending → generating
 *   → generated | failed, avec `expires_at` (lien de téléchargement borné)
 *   et traçabilité (requested_by, error).
 *
 * Toutes les données sont tenant-scoped (company_id uuid indexé), FKs
 * composites anti cross-tenant sur les stations.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fuel_report_snapshots')) {
            Schema::create('fuel_report_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('station_id')->nullable()->index();

                // daily_volumes|shift_summary|sales_summary|stock_status|variance_summary
                $table->string('report_type', 40);
                $table->date('snapshot_date')->index();
                $table->jsonb('payload');
                $table->dateTime('computed_at');
                $table->timestamps();

                $table->unique(
                    ['company_id', 'station_id', 'report_type', 'snapshot_date'],
                    'fuel_report_snapshots_unique'
                );

                $table->foreign(['station_id', 'company_id'], 'fuel_report_snapshots_station_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_stations')
                    ->cascadeOnDelete();
            });
        }

        if (! schemaTableExists('fuel_report_exports')) {
            Schema::create('fuel_report_exports', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('station_id')->nullable()->index();

                $table->string('report_type', 40);
                $table->string('status', 16)->default('pending'); // pending|generating|generated|failed
                $table->string('file_path', 500)->nullable();
                $table->date('report_date')->nullable();
                $table->unsignedInteger('requested_by')->nullable();
                $table->dateTime('expires_at')->nullable();
                $table->text('error')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'status'], 'fuel_report_exports_company_status_idx');

                $table->foreign(['station_id', 'company_id'], 'fuel_report_exports_station_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_stations')
                    ->cascadeOnDelete();
            });
        }

        $this->addChecks();
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_report_exports');
        Schema::dropIfExists('fuel_report_snapshots');
    }

    private function constraintExists(string $name): bool
    {
        $row = DB::selectOne('SELECT 1 FROM pg_constraint WHERE conname = ?', [$name]);

        return $row !== null;
    }

    private function addChecks(): void
    {
        foreach ([
            'fuel_report_snapshots' => [
                'fuel_report_snapshots_type_check' => "report_type IN ('daily_volumes', 'shift_summary', 'sales_summary', 'stock_status', 'variance_summary')",
            ],
            'fuel_report_exports' => [
                'fuel_report_exports_type_check' => "report_type IN ('daily_volumes', 'shift_summary', 'sales_summary', 'stock_status', 'variance_summary', 'referential')",
                'fuel_report_exports_status_check' => "status IN ('pending', 'generating', 'generated', 'failed')",
            ],
        ] as $table => $constraints) {
            $schema = resolveTableSchema($table);

            if ($schema === null) {
                continue;
            }

            foreach ($constraints as $name => $check) {
                if ($this->constraintExists($name)) {
                    continue;
                }

                DB::statement("ALTER TABLE {$schema}.{$table} ADD CONSTRAINT {$name} CHECK ({$check})");
            }
        }
    }
};
