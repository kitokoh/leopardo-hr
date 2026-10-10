<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Verticale FUNDRAISING — annuaires publics (schema public, cross-tenant).
 *
 * Les données métier vivent dans les schemas TENANT (search_path) : une
 * résolution publique doit d'abord trouver le tenant, puis lire via
 * `TenantManager::withinTenant()` (pattern BOS-050 PublicTenantResolver).
 *
 * - `fundraiser_public_links` : slug court → company_id (lien de cagnotte
 *   façon GoFundMe, spec SOLUTION_FUNDRAISING.md §3.1). Écrit à la
 *   publication de la cagnotte, statut miroir tenu à jour aux transitions
 *   (une cagnotte non publiée n'a pas d'entrée → 404 anti-énumération).
 * - `fundraising_payment_routes` : (provider, provider_reference) →
 *   (company_id, contribution_reference) — les webhooks arrivent hors
 *   contexte tenant ; cet annuaire les route vers le bon schema (spec §4.3)
 *   et sert au polling public `GET /public/contributions/{reference}`.
 *
 * Tables SANS trait BelongsToCompany (pas d'isolation par company_id ici —
 * ce sont des annuaires plateforme, exceptions déclarées dans
 * dev-hub/governance/tenant-scope-exceptions.json). Idempotente + down()
 * limité à ce fichier (leçon #8207).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fundraiser_public_links')) {
            Schema::create('fundraiser_public_links', function (Blueprint $table): void {
                $table->id();
                $table->string('slug', 160);
                $table->uuid('company_id');
                $table->string('status', 20)->default('active');
                $table->timestamps();

                $table->unique('slug', 'fundraiser_public_links_slug_unique');
                $table->index('company_id', 'fundraiser_public_links_company_index');
            });

            DB::statement("COMMENT ON TABLE fundraiser_public_links IS 'Annuaire public des cagnottes (verticale FUNDRAISING) : slug unique global -> company_id, statut miroir (active|completed|closed), ecrit a la publication.';");
        }

        if (! schemaTableExists('fundraising_payment_routes')) {
            Schema::create('fundraising_payment_routes', function (Blueprint $table): void {
                $table->id();
                $table->string('provider', 30);
                $table->string('provider_reference', 190);
                $table->uuid('company_id');
                $table->string('contribution_reference', 32);
                $table->timestamps();

                $table->unique(['provider', 'provider_reference'], 'fundraising_payment_routes_provider_ref_unique');
                $table->unique('contribution_reference', 'fundraising_payment_routes_contribution_ref_unique');
            });

            DB::statement("COMMENT ON TABLE fundraising_payment_routes IS 'Annuaire de routage paiement (verticale FUNDRAISING) : (provider, provider_reference) -> (company_id, contribution_reference) pour webhooks hors contexte tenant et polling public.';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fundraising_payment_routes');
        Schema::dropIfExists('fundraiser_public_links');
    }
};
