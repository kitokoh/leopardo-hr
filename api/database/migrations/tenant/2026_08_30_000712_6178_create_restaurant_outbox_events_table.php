<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6178 (RESTO-213) - RestaurantManager : outbox d evenements (pattern #5741).
 *
 * Fiabilite des files de la verticale : un effet metier (notification, export,
 * callback provider, relance…) est d abord PERSISTE dans cette table APRES le
 * commit de la transaction metier, puis consomme de facon asynchrone et
 * idempotente par le dispatcher. Un crash entre commit et consommation ne perd
 * rien (replay) ; la contrainte unique (company_id, idempotency_key) garantit
 * zero doublon meme en cas de rejeu.
 *
 * `company_id` non nullable ; statuts pending/processing/sent/failed ;
 * `available_at` porte le backoff (transitoire) ; attempts borne le dead-letter ;
 * payload redige (`payload_redacted`, json compatible pgsql/sqlite).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('restaurant_outbox_events')) {
            Schema::create('restaurant_outbox_events', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('event_type', 120);
                $table->json('payload_redacted')->nullable();
                $table->string('status', 20)->default('pending');
                $table->timestamp('available_at')->nullable();
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->text('last_error')->nullable();
                $table->string('idempotency_key', 64);

                $table->timestamps();

                $table->unique(['company_id', 'idempotency_key'], 'restaurant_outbox_events_company_idempotency_key_unique');
                $table->index(['company_id', 'status', 'available_at'], 'restaurant_outbox_events_company_status_available_idx');
            });

            DB::statement("COMMENT ON TABLE restaurant_outbox_events IS 'Outbox des evenements RestaurantManager : persistance apres commit, consommation asynchrone idempotente (RESTO-213/#6178).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_outbox_events');
    }
};
