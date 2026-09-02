<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5810 (FUEL-016) — intégration CRM FuelStation (comptes
 * professionnels, visites, consentements).
 *
 * `fuel_professional_accounts` : comptes professionnels (B2B) liés à la
 * station — entreprises clientes, flottes, contrats. Champs de contact
 * CHIFFRÉS (cast encrypted) et consentement marketing explicite par canal.
 * L'identifiant est le couple (code, company_id) — jamais de lecture des
 * leads du CRM commercial Leopardo (isolation dual-context, ADR-CRM).
 *
 * `fuel_account_visits` : visites commerciales / opérationnelles rattachées
 * à un compte (agent terrain, fidélité). Append-only, idempotent par
 * external_id (rejeu sans doublon).
 *
 * Les événements tenant-scoped (`fuel.account.upserted.v1`,
 * `fuel.visit.recorded.v1`, `fuel.consent.updated.v1`) sont publiés par
 * l'outbox FuelStation (FUEL-015) — le CRM client (BC-11) les consomme via
 * son propre listener, sans import croisé.
 *
 * Migration additive + idempotente (garde schemaTableExists #1962/#5431),
 * FKs composites anti cross-tenant (pattern FUEL-002/003).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fuel_professional_accounts')) {
            Schema::create('fuel_professional_accounts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('station_id')->nullable()->index();

                $table->string('code', 60);
                $table->string('name', 200);
                $table->string('industry', 60)->nullable();
                // Contact chiffré (RGPD) : email/téléphone du référent.
                $table->text('contact_encrypted')->nullable();
                // Consentements marketing explicites par canal (chiffrés).
                $table->text('consents')->nullable();
                // active | inactive | archived
                $table->string('status', 20)->default('active');
                $table->string('external_id', 120)->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'fuel_professional_accounts_company_code_unique');
                $table->unique(['company_id', 'external_id'], 'fuel_professional_accounts_ext_unique');
                $table->unique(['id', 'company_id'], 'fuel_professional_accounts_id_company_unique');

                $table->foreign(['station_id', 'company_id'], 'fuel_accounts_station_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_stations')
                    ->cascadeOnDelete();
            });

            DB::statement("COMMENT ON TABLE fuel_professional_accounts IS 'Comptes professionnels B2B FuelStation (contact chiffré, consentements explicites, jamais de leads CRM Leopardo) — FUEL-016 (#5810).'");
        }

        if (! schemaTableExists('fuel_account_visits')) {
            Schema::create('fuel_account_visits', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('account_id')->index();

                $table->timestampTz('visited_at')->useCurrent();
                $table->string('purpose', 40)->default('commercial');
                $table->text('notes_redacted')->nullable();
                $table->string('external_id', 120)->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();

                $table->unique(['company_id', 'external_id'], 'fuel_account_visits_ext_unique');
                $table->index(['company_id', 'account_id', 'visited_at'], 'fuel_visits_account_visited_idx');

                $table->foreign(['account_id', 'company_id'], 'fuel_visits_account_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_professional_accounts')
                    ->cascadeOnDelete();
            });

            DB::statement("COMMENT ON TABLE fuel_account_visits IS 'Visites commerciales/opérationnelles rattachées aux comptes professionnels (append-only, idempotentes) — FUEL-016 (#5810).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_account_visits');
        Schema::dropIfExists('fuel_professional_accounts');
    }
};
