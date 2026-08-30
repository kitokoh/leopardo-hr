<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6175 (RESTO-210) - RestaurantManager : reservations.
 *
 * `restaurant_reservations` : reservations de tables par branche - reference
 * unique par tenant, `idempotency_key` unique (retry sans doublon), index
 * (company_id, branch_id, reserved_at) pour la recherche par creneau.
 * `contact_name`/`contact_phone` portent le contact de la reservation ; les
 * notes sont redigees (`notes_redacted`).
 *
 * Tenant-scoped, sans FK : colonnes simples + index nommes. Idempotente + down() complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('restaurant_reservations')) {
            Schema::create('restaurant_reservations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('branch_id');
                $table->string('reference', 40);
                $table->unsignedBigInteger('customer_contact_id')->nullable();
                $table->string('contact_name', 150);
                $table->string('contact_phone', 40);
                $table->timestamp('reserved_at');
                $table->unsignedSmallInteger('covers')->default(1);
                $table->unsignedBigInteger('table_id')->nullable();
                $table->unsignedBigInteger('zone_id')->nullable();
                $table->string('status', 20)->default('pending');
                $table->unsignedInteger('deposit_minor')->nullable();
                $table->text('notes_redacted')->nullable();
                $table->string('idempotency_key', 64)->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'reference'], 'restaurant_reservations_company_reference_unique');
                $table->unique(['company_id', 'idempotency_key'], 'restaurant_reservations_company_idempotency_key_unique');
                $table->index(['company_id', 'branch_id', 'reserved_at'], 'restaurant_reservations_company_branch_reserved_at_idx');
            });

            DB::statement("COMMENT ON TABLE restaurant_reservations IS 'Reservations de tables - reference et idempotency_key uniques par tenant (RESTO-210/#6175).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_reservations');
    }
};
