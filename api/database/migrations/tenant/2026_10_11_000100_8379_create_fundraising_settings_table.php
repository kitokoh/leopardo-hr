<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Verticale FUNDRAISING (cagnottes solidaires) — table
 * `fundraising_settings`.
 *
 * Paramétrage tenant du module (spec SOLUTION_FUNDRAISING.md §6) : une
 * seule ligne par entreprise (company_id unique), résolue via la compagnie
 * courante — jamais par un id d'URL (fail-closed #3727, même pattern que
 * `accounting_settings` #5232).
 *
 * - `enabled_methods` JSON : moyens de paiement offerts aux contributeurs
 *   (`card` Stripe, `mobile_money`, `manual`) — consommé par
 *   InitiateContributionAction et exposé à la surface publique ;
 * - `default_min_amount` / `default_max_amount` : garde-fous par défaut
 *   quand la cagnotte ne surcharge pas (fallback : config/fundraising.php) ;
 * - `default_suggested_amounts` JSON : montants suggérés pré-remplis à la
 *   création d'une cagnotte (façon GoFundMe) ;
 * - `allow_anonymous` / `allow_messages` / `show_supporters` : options de
 *   la surface publique.
 *
 * Tenant-scoped (`company_id` uuid), sans FK (conventions migrations
 * tenant §2.6). Idempotente + down() qui ne détruit que ce que ce fichier
 * crée (leçon #8207).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fundraising_settings')) {
            Schema::create('fundraising_settings', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');

                $table->json('enabled_methods');
                $table->decimal('default_min_amount', 15, 2)->nullable();
                $table->decimal('default_max_amount', 15, 2)->nullable();
                $table->json('default_suggested_amounts')->nullable();
                $table->boolean('allow_anonymous')->default(true);
                $table->boolean('allow_messages')->default(true);
                $table->boolean('show_supporters')->default(true);

                $table->timestamps();

                $table->unique('company_id', 'fundraising_settings_company_unique');
            });

            DB::statement("COMMENT ON TABLE fundraising_settings IS 'Paramétrage tenant de la verticale FUNDRAISING : moyens de paiement offerts, garde-fous par défaut, montants suggérés, options de la surface publique — une ligne unique par entreprise.';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fundraising_settings');
    }
};
