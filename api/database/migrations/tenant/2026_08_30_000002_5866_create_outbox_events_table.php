<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MAT-008 (#5866) — Runtime inbox/outbox/queues fiable (BC-01 PLATFORM).
 *
 * Table tenant `outbox_events` : file de sortie transactionnelle générique
 * pour les événements de plateforme (CompanyCreated, SubscriptionPaid) et
 * de tout BC qui souhaite la même garantie (idempotence, retry borné,
 * dead-letter, replay, lease anti double-traitement).
 *
 * La déduplication est portée par deux index partiels :
 *  - (company_id, event_type, idempotency_key) pour les événements tenant ;
 *  - (event_type, idempotency_key) pour les événements plateforme
 *    (company_id NULL — Postgres ne déduplique pas les NULL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->nullable()->index();
            $table->string('event_type', 120);
            $table->string('aggregate_type', 120)->nullable();
            $table->string('aggregate_id', 120)->nullable();
            $table->jsonb('payload');
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(5);
            $table->timestamp('available_at')->useCurrent();
            $table->timestamp('lease_until')->nullable();
            $table->text('last_error')->nullable();
            $table->string('idempotency_key', 190);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            // Réclamer : événements dus non en lease (matrice (status, available_at)).
            $table->index(['status', 'available_at'], 'outbox_events_status_available_index');
            // Dédup tenant.
            $table->unique(['company_id', 'event_type', 'idempotency_key'], 'outbox_events_tenant_dedup_unique');
            // Dédup plateforme (company_id NULL).
            $table->unique(['event_type', 'idempotency_key'], 'outbox_events_platform_dedup_unique')
                ->whereNull('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
    }
};
