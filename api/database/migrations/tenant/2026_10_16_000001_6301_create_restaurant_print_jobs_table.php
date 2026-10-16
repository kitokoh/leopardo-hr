<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * REST-OS-2 (Mission Leopardo Restaurant OS) — Travaux d'impression thermique.
 *
 * `restaurant_print_jobs` porte l'idempotence de l'impression cuisine : la
 * contrainte unique (company_id, order_id, ticket_kind, revision) garantit
 * qu'un même ticket n'est jamais mis en file deux fois, quelle que soit la
 * concurrence (deux appels simultanés → un seul travail). Le statut n'est
 * `printed` que sur accusé du bridge local (voir AckPrintJobAction).
 *
 * Tenant-scoped, sans FK : colonnes simples + index nommés. Idempotente +
 * down() complet. Schéma résolu via resolveTableSchema() (règle #1613).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('restaurant_print_jobs')) {
            return;
        }

        Schema::create('restaurant_print_jobs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();

            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('order_id');
            $table->string('ticket_kind', 20)->default('kitchen');
            $table->unsignedInteger('revision')->default(1);
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->string('printer_ref', 120)->nullable();
            $table->text('last_error')->nullable();
            $table->string('payload_hash', 64)->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['company_id', 'order_id', 'ticket_kind', 'revision'],
                'restaurant_print_jobs_company_order_kind_revision_unique'
            );
            $table->index(['company_id', 'branch_id'], 'restaurant_print_jobs_company_branch_idx');
            $table->index(['company_id', 'status'], 'restaurant_print_jobs_company_status_idx');
        });

        DB::statement("COMMENT ON TABLE restaurant_print_jobs IS 'Travaux d''impression thermique (bridge cuisine) - unique (tenant, commande, type, revision) : jamais de doublon (REST-OS-2).';");
    }

    public function down(): void
    {
        $schema = resolveTableSchema('restaurant_print_jobs');

        if ($schema === null) {
            return;
        }

        Schema::dropIfExists("{$schema}.restaurant_print_jobs");
    }
};
