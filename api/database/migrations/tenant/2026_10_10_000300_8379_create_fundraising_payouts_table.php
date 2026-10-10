<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Verticale FUNDRAISING — table `fundraising_payouts` (reversements au
 * bénéficiaire).
 *
 * Spec : docs/specifications/SOLUTION_FUNDRAISING.md §3.3.
 *
 * - `reference` unique `FP-XXXXXXXX` ;
 * - `status` `requested|processing|paid|failed|cancelled` ;
 * - règle métier : Σ payouts `requested|processing|paid` d'une cagnotte ≤
 *   solde disponible (vérifiée en transaction avec lockForUpdate) ;
 * - `recipient_account` : n° mobile money ou IBAN/RIB selon `method`.
 *
 * Tenant-scoped, sans FK (conventions migrations tenant §2.6). Idempotente
 * + down() limité à ce fichier (leçon #8207).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fundraising_payouts')) {
            Schema::create('fundraising_payouts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('fundraiser_id');

                $table->string('reference', 32);
                $table->decimal('amount', 15, 2);
                $table->string('currency', 3);
                $table->string('method', 20);
                $table->string('recipient_name', 190);
                $table->string('recipient_account', 190);
                $table->string('status', 20)->default('requested');
                $table->string('provider_reference', 190)->nullable();
                $table->string('failure_reason', 500)->nullable();

                $table->unsignedBigInteger('requested_by')->nullable();
                $table->unsignedBigInteger('processed_by')->nullable();
                $table->timestamp('processed_at')->nullable();

                $table->timestamps();

                $table->unique('reference', 'fundraising_payouts_reference_unique');
                $table->index(['fundraiser_id', 'status'], 'fundraising_payouts_fundraiser_status_index');
                $table->index(['company_id', 'status'], 'fundraising_payouts_company_status_index');
            });

            DB::statement("COMMENT ON TABLE fundraising_payouts IS 'Reversements des cagnottes vers le bénéficiaire (verticale FUNDRAISING) : workflow requested|processing|paid|failed|cancelled, règle de solde vérifiee en transaction.';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fundraising_payouts');
    }
};
