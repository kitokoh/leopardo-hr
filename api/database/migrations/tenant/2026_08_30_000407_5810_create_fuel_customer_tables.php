<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5810 (FUEL-016) — intégration CRM & marketing client.
 *
 * Deux tables additives, tenant-scoped (FK composites anti cross-tenant) :
 *  - `fuel_customers` : comptes professionnels/clients de la station
 *    (jamais les leads commerciaux Leopardo — distinction CRM tenant vs
 *    CRM plateforme verrouillée) ; consentement marketing EXPLICITE
 *    horodaté (`opted_in_at`/`opted_out_at`), points de fidélité entiers,
 *    `external_id` UNIQUE (company_id, external_id) pour le rejeu ;
 *  - `fuel_customer_visits` : visites/activités client, `idempotency_key`
 *    UNIQUE (company_id, idempotency_key) → crédit de fidélité unique par
 *    visite (aucun doublon au rejeu).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fuel_customers')) {
            Schema::create('fuel_customers', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('station_id')->nullable()->index();
                $table->string('name', 160);
                $table->string('contact_email', 255)->nullable();
                $table->string('phone', 40)->nullable();
                $table->boolean('marketing_consent')->default(false);
                $table->timestampTz('opted_in_at')->nullable();
                $table->timestampTz('opted_out_at')->nullable();
                $table->unsignedBigInteger('loyalty_points')->default(0);
                // active|inactive
                $table->string('status', 20)->default('active');
                $table->string('external_id', 120)->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'external_id'], 'fuel_customers_external_unique');
                $table->index(['company_id', 'station_id'], 'fuel_customers_company_station_idx');
                $table->index(['company_id', 'status'], 'fuel_customers_company_status_idx');

                $table->foreign(['station_id', 'company_id'], 'fuel_customers_station_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_stations')
                    ->cascadeOnDelete();
                $table->foreign('created_by', 'fuel_customers_created_by_fk')
                    ->references('id')
                    ->on('employees')
                    ->nullOnDelete();
            });
        }

        if (! schemaTableExists('fuel_customer_visits')) {
            Schema::create('fuel_customer_visits', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('customer_id')->index();
                $table->unsignedBigInteger('station_id')->index();
                $table->timestampTz('visited_at')->useCurrent();
                $table->string('notes', 1000)->nullable();
                $table->string('idempotency_key', 160)->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'idempotency_key'], 'fuel_customer_visits_idempotency_unique');
                $table->index(['company_id', 'customer_id', 'visited_at'], 'fuel_customer_visits_company_customer_time_idx');

                $table->foreign(['customer_id', 'company_id'], 'fuel_customer_visits_customer_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_customers')
                    ->cascadeOnDelete();
                $table->foreign(['station_id', 'company_id'], 'fuel_customer_visits_station_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_stations')
                    ->cascadeOnDelete();
                $table->foreign('created_by', 'fuel_customer_visits_created_by_fk')
                    ->references('id')
                    ->on('employees')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_customer_visits');
        Schema::dropIfExists('fuel_customers');
    }
};
