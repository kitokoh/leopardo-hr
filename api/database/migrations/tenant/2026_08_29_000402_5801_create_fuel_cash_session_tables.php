<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5801 (FUEL-007) — sessions de caisse et clôture.
 *
 * `fuel_cash_sessions` : session de caisse d'une station (ouverture par le
 * pompiste, mouvements in/out, clôture avec écart calculé serveur, statut
 * open|closed|approved). `station_id` BIGINT nullable avec FK COMPOSITE
 * (station_id, company_id) → fuel_stations(id, company_id) (pattern
 * FUEL-002/003 — anti cross-tenant).
 *
 * `fuel_cash_session_movements` : mouvements de la session (type in|out,
 * montant strictement positif, motif). Écrits uniquement session ouverte.
 *
 * Clôture IDEMPOTENTE : une session déjà close renvoie son état sans
 * recalcul ni double effet (statut terminal). L'écart (variance) est
 * calculé côté serveur — jamais fourni par le client. L'approbation
 * (manager) verrouille l'état ; l'événement `FuelCashSessionClosed`
 * (contrat Accounting, FUEL-015) est émis à la clôture.
 *
 * Migration additive + idempotente (garde schemaTableExists #1962/#5431),
 * clés primaires bigint ($table->id()), company_id uuid indexé, CHECKs
 * gardés pg_constraint. Rollback : suppression des deux tables (FK session
 * d'abord).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fuel_cash_sessions')) {
            Schema::create('fuel_cash_sessions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('station_id')->nullable()->index();
                $table->unsignedInteger('opened_by')->index();
                $table->timestampTz('opened_at')->useCurrent();
                $table->unsignedInteger('closed_by')->nullable();
                $table->timestampTz('closed_at')->nullable();
                $table->decimal('opening_balance', 14, 2)->default(0);
                $table->decimal('closing_balance', 14, 2)->nullable();
                $table->decimal('expected_balance', 14, 2)->nullable();
                $table->decimal('variance', 14, 2)->nullable();
                $table->string('status', 20)->default('open'); // open|closed|approved
                $table->unsignedInteger('approved_by')->nullable();
                $table->timestampTz('approved_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'status', 'opened_at'], 'fuel_cash_sessions_status_idx');

                // FK composite anti cross-tenant (pattern FUEL-002/003).
                $table->foreign(['station_id', 'company_id'], 'fuel_cash_sessions_station_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_stations')
                    ->cascadeOnDelete();
                $table->foreign('opened_by', 'fuel_cash_sessions_opened_by_fk')
                    ->references('id')
                    ->on('employees')
                    ->cascadeOnDelete();
            });
        }

        if (! schemaTableExists('fuel_cash_session_movements')) {
            Schema::create('fuel_cash_session_movements', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('session_id')->index();
                $table->string('type', 10); // in|out
                $table->decimal('amount', 14, 2);
                $table->string('reason', 255);
                $table->unsignedInteger('created_by')->nullable();
                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();

                $table->foreign('session_id', 'fuel_cash_session_movements_session_fk')
                    ->references('id')
                    ->on('fuel_cash_sessions')
                    ->cascadeOnDelete();
            });
        }

        $this->addChecks();
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_cash_session_movements');
        Schema::dropIfExists('fuel_cash_sessions');
    }

    private function constraintExists(string $name): bool
    {
        $row = DB::selectOne('SELECT 1 FROM pg_constraint WHERE conname = ?', [$name]);

        return $row !== null;
    }

    private function addChecks(): void
    {
        foreach ([
            'fuel_cash_sessions' => [
                'fuel_cash_sessions_status_check' => "status IN ('open', 'closed', 'approved')",
            ],
            'fuel_cash_session_movements' => [
                'fuel_cash_session_movements_type_check' => "type IN ('in', 'out')",
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
