<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FUEL-007 (#5801) — Sessions de caisse et clôture.
 *
 * `fuel_cash_sessions` : session de caisse d'une station (ouverture par le
 * pompiste, mouvements in/out, clôture avec écart calculé serveur, statut
 * open|closed|approved). `station_id` (uuid, nullable) résolu par FUEL-002.
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
 * Migration additive + idempotente (garde #1962/#5431), réf. issue dans le
 * nom. Rollback : suppression des deux tables (FK session d'abord).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fuel_cash_sessions')) {
            Schema::create('fuel_cash_sessions', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('company_id')->index();
                $table->uuid('station_id')->nullable()->index();
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
            });
        }

        if (! schemaTableExists('fuel_cash_session_movements')) {
            Schema::create('fuel_cash_session_movements', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('company_id')->index();
                $table->uuid('session_id')->index();
                $table->string('type', 10); // in|out
                $table->decimal('amount', 14, 2);
                $table->string('reason', 255);
                $table->unsignedInteger('created_by')->nullable();
                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();
            });
        }

        $this->addForeignKeyIfMissing(
            'fuel_cash_session_movements',
            'fuel_cash_session_movements_session_fk',
            'session_id',
            'fuel_cash_sessions',
            'id'
        );
        $this->addForeignKeyIfMissing(
            'fuel_cash_sessions',
            'fuel_cash_sessions_opened_by_fk',
            'opened_by',
            'employees',
            'id'
        );
        $this->addForeignKeyIfMissing(
            'fuel_cash_sessions',
            'fuel_cash_sessions_station_fk',
            'station_id',
            'fuel_stations',
            'id'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_cash_session_movements');
        Schema::dropIfExists('fuel_cash_sessions');
    }

    private function addForeignKeyIfMissing(
        string $table,
        string $constraint,
        string $column,
        string $references,
        string $referencedColumn,
    ): void {
        if (! schemaTableExists($references)) {
            return;
        }

        $exists = DB::selectOne(
            'SELECT 1 FROM information_schema.table_constraints
             WHERE constraint_name = ? AND table_schema = ANY (current_schemas(false))',
            [$constraint]
        );

        if ($exists === null) {
            DB::statement(
                "ALTER TABLE {$table} ADD CONSTRAINT {$constraint}
                 FOREIGN KEY ({$column}) REFERENCES {$references} ({$referencedColumn}) ON DELETE CASCADE"
            );
        }
    }
};
