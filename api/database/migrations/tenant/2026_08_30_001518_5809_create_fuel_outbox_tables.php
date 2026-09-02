<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5809 (FUEL-015) — contrat Accounting : outbox des événements
 * FuelStation versionnés.
 *
 * `fuel_outbox_events` : file d'événements de contrat destinés au module
 * Accounting (et aux intégrations). Pattern aligné sur l'outbox CRM
 * (#5741) et la leçon BC-14 (lease de re-claim) :
 * - publication APRÈS le commit métier (jamais dedans) ;
 * - `idempotency_key` unique par tenant (déduplication des rejets) ;
 * - statuts pending → processing → sent | failed (dead-letter après
 *   MAX_ATTEMPTS, retry avec backoff exponentiel + jitter) ;
 * - lease de 15 min : un événement `processing` orphelin (worker crash)
 *   est re-claimé sans réinitialiser le budget de tentatives.
 *
 * Échec toujours isolé du flux opérationnel : la publication d'un
 * événement ne peut pas faire échouer la vente/clôture/livraison.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fuel_outbox_events')) {
            Schema::create('fuel_outbox_events', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('event_type', 120);
                $table->string('aggregate_type', 60)->nullable();
                $table->unsignedBigInteger('aggregate_id')->nullable();
                $table->jsonb('payload');
                $table->string('status', 16)->default('pending'); // pending|processing|sent|failed
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->dateTime('available_at')->index();
                $table->text('last_error')->nullable();
                $table->dateTime('processed_at')->nullable();
                $table->string('idempotency_key', 64);
                $table->timestamps();

                $table->unique(['company_id', 'idempotency_key'], 'fuel_outbox_idem_key_unique');
                $table->index(['company_id', 'status'], 'fuel_outbox_company_status_idx');
                $table->index(['company_id', 'available_at'], 'fuel_outbox_company_due_idx');
            });
        }

        $this->addChecks();
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_outbox_events');
    }

    private function constraintExists(string $name): bool
    {
        $row = DB::selectOne('SELECT 1 FROM pg_constraint WHERE conname = ?', [$name]);

        return $row !== null;
    }

    private function addChecks(): void
    {
        $schema = resolveTableSchema('fuel_outbox_events');

        if ($schema === null) {
            return;
        }

        $constraints = [
            'fuel_outbox_status_check' => "status IN ('pending', 'processing', 'sent', 'failed')",
        ];

        foreach ($constraints as $name => $check) {
            if ($this->constraintExists($name)) {
                continue;
            }

            DB::statement("ALTER TABLE {$schema}.fuel_outbox_events ADD CONSTRAINT {$name} CHECK ({$check})");
        }
    }
};
