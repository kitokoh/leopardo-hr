<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #5813 (FUEL-019) — FuelStation : outbox événementielle des notifications.
 *
 * `fuel_outbox_events` : pattern CRM #5741 (idempotency_key unique par
 * tenant) — consommée par BC-13 (notifications multi-canal). Événements :
 * `fuel.anomaly.meter.v1`, `fuel.anomaly.missing_close.v1`,
 * `fuel.anomaly.variance.v1`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fuel_outbox_events')) {
            Schema::create('fuel_outbox_events', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('event_type', 80);
                $table->jsonb('payload_redacted')->nullable();
                $table->string('status', 20)->default('pending');
                $table->unsignedInteger('attempts')->default(0);
                $table->timestamp('available_at')->nullable();
                $table->text('last_error')->nullable();
                $table->string('idempotency_key', 100);

                $table->timestamps();

                $table->unique(['company_id', 'idempotency_key'], 'fuel_outbox_events_company_idempotency_unique');
                $table->index(['company_id', 'status', 'available_at'], 'fuel_outbox_events_company_status_available_idx');
            });

            DB::statement("COMMENT ON TABLE fuel_outbox_events IS 'Outbox des evenements FuelStation - deduplication par idempotency_key (FUEL-019/#5813).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_outbox_events');
    }
};
