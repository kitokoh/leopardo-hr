<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Verticale FUNDRAISING — table `fundraising_contributions`.
 *
 * Spec : docs/specifications/SOLUTION_FUNDRAISING.md §3.2.
 *
 * - `reference` publique unique (`FC-XXXXXXXX`) : polling contributeur sans
 *   compte, jamais d'id séquentiel exposé (anti-énumération) ;
 * - `status` `pending|completed|failed|refunded` — seul `completed`
 *   crédite le compteur de la cagnotte (une seule fois, transaction) ;
 * - coordonnées contributeur (`contributor_email/phone`) non exposées
 *   publiquement (RGPD) ; `is_anonymous` masque nom + montant sur le mur ;
 * - `provider_reference` = id session Stripe / référence opérateur mobile
 *   money, utilisé pour la re-conciliation active (`verify`).
 *
 * Tenant-scoped, sans FK (conventions migrations tenant §2.6). Idempotente
 * + down() limité à ce fichier (leçon #8207).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fundraising_contributions')) {
            Schema::create('fundraising_contributions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('fundraiser_id');

                $table->string('reference', 32);
                $table->decimal('amount', 15, 2);
                $table->string('currency', 3);
                $table->string('payment_method', 20);
                $table->string('provider', 30);
                $table->string('provider_reference', 190)->nullable();
                $table->string('status', 20)->default('pending');

                $table->string('contributor_name', 190)->nullable();
                $table->string('contributor_email', 190)->nullable();
                $table->string('contributor_phone', 40)->nullable();
                $table->boolean('is_anonymous')->default(false);
                $table->string('message', 500)->nullable();
                $table->json('metadata')->nullable();

                $table->timestamp('paid_at')->nullable();
                $table->timestamps();

                $table->unique('reference', 'fundraising_contributions_reference_unique');
                $table->index(['fundraiser_id', 'status'], 'fundraising_contributions_fundraiser_status_index');
                $table->index(['company_id', 'status'], 'fundraising_contributions_company_status_index');
                $table->index('provider_reference', 'fundraising_contributions_provider_ref_index');
            });

            DB::statement("COMMENT ON TABLE fundraising_contributions IS 'Contributions aux cagnottes (verticale FUNDRAISING) : référence publique unique, statut pending|completed|failed|refunded, seul completed crédite le compteur (transaction, idempotent).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fundraising_contributions');
    }
};
