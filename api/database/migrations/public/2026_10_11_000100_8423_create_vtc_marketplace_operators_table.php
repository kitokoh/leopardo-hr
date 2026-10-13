<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8423 (M-10) — Marketplace publique VTC/taxi : annuaire des opérateurs
 * OPT-IN (schema public, cross-tenant).
 *
 * Marqueur « opérateur VTC publié sur marketplace » : équivalent VTC du
 * jeton boutique actif de la marketplace travel (#7737) — mais posé en
 * schema PUBLIC, sur le pattern des annuaires plateforme FUNDRAISING
 * (#8379, `fundraiser_public_links`) : l'annuaire est la SEULE donnée
 * cross-tenant lue sans contexte tenant ; les données métier (courses,
 * grilles) restent dans le schema tenant et sont toujours lues via
 * `TenantManager::withinTenant()` APRÈS résolution du tenant par ressource
 * (pattern BOS-050 PublicTenantResolver).
 *
 * Éligibilité publique d'un opérateur (M-11) : ligne `active` ici
 * + société ni suspendue ni expirée + feature `vtc` activée
 * (`Company::hasFeature`, kill switch plateforme respecté). Une société se
 * retire de la marketplace en passant sa ligne à `inactive` (ou en la
 * supprimant) — la surface publique se referme aussitôt (fail-closed).
 *
 * Table SANS trait BelongsToCompany (annuaire plateforme : `company_id`
 * est un pointeur de routage, pas une clé d'isolation — exception déclarée
 * dans dev-hub/governance/tenant-scope-exceptions.json). Réentrante
 * (schemaTableExists), aucune FK, down() limité à cette table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('vtc_marketplace_operators')) {
            Schema::create('vtc_marketplace_operators', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->string('status', 20)->default('active');
                $table->timestamps();

                // Un opt-in par société ; l'unicité sert aussi d'index de
                // résolution company -> publication (jointure annuaire).
                $table->unique('company_id', 'vtc_marketplace_operators_company_unique');
                $table->index('status', 'vtc_marketplace_operators_status_idx');
            });

            DB::statement("COMMENT ON TABLE vtc_marketplace_operators IS 'Annuaire public des operateurs VTC opt-in de la marketplace (#8423) : company_id -> statut de publication (active|inactive), eligibilite completee par companies.status + feature vtc.';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vtc_marketplace_operators');
    }
};
