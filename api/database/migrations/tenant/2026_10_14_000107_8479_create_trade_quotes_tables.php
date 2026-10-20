<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8479 (TRADE-004) — BC-36 TRADE : tables tenant `trade_quotes` + `trade_quote_lines`.
 *
 * OFFRE COMMERCIALE — objet UNIQUE servant a la fois de REPONSE a une RFQ
 * (`direction = incoming`, cote acheteur) et d'OFFRE PROACTIVE du vendeur
 * (`direction = outgoing`, `trade_rfq_id` null). C'est le noyau partage
 * acheteur/vendeur : le meme modele, vu des deux cotes.
 *
 * VERSIONNEMENT : `version` + `supersedes_id` (auto-reference). Une revision
 * cree une NOUVELLE ligne qui remplace l'ancienne — l'historique de
 * negociation reste integralement consultable (exigence mission : gerer
 * versions, validite et conditions des offres).
 *
 * `subtotal_minor` / `total_minor` sont FIGES cote SERVEUR (recalcules des
 * lignes a l'ecriture, jamais acceptes du client) : un client ne peut pas
 * imposer un prix. `currency` + `incoterm` conditionnent la comparabilite
 * (§7 : deux offres de perimetres de cout differents ne sont PAS declarees
 * comparables).
 *
 * Etats : `draft|submitted|under_review|negotiating|accepted|rejected|expired|withdrawn`.
 *
 * @see docs/specifications/VERTICAL_TRADE_OS.md §4.3
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('trade_quotes')) {
            Schema::create('trade_quotes', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('reference', 40);
                $table->unsignedInteger('version')->default(1);
                $table->unsignedBigInteger('supersedes_id')->nullable();

                $table->string('direction', 20);

                $table->unsignedBigInteger('trade_rfq_id')->nullable();
                $table->unsignedBigInteger('trade_rfq_invitation_id')->nullable();
                $table->unsignedBigInteger('trade_deal_id')->nullable();
                $table->unsignedBigInteger('trade_counterparty_id')->nullable();

                $table->string('status', 20)->default('draft');

                $table->char('currency', 3);
                $table->string('incoterm', 3)->nullable();

                $table->date('validity_until')->nullable();
                $table->unsignedInteger('lead_time_days')->nullable();
                $table->string('payment_terms', 200)->nullable();

                // Totaux FIGES serveur (minor units) — jamais acceptes du client.
                $table->bigInteger('subtotal_minor')->default(0);
                $table->bigInteger('total_minor')->default(0);

                $table->text('notes')->nullable();

                $table->unsignedBigInteger('created_by_employee_id')->nullable();

                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamp('withdrawn_at')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->unique(['company_id', 'reference', 'version'], 'trade_quotes_company_reference_version_unique');
                $table->index(['company_id', 'status', 'created_at'], 'trade_quotes_company_status_date_idx');
                $table->index(['company_id', 'trade_rfq_id'], 'trade_quotes_company_rfq_idx');
                $table->index(['company_id', 'direction'], 'trade_quotes_company_direction_idx');
            });

            DB::statement("COMMENT ON TABLE trade_quotes IS 'Offres commerciales Trade (reponses RFQ et offres proactives) - versioning par supersedes_id, totaux figes serveur, Incoterm/devise conditionnant la comparabilite (TRADE-004/#8479).';");
        }

        if (! schemaTableExists('trade_quote_lines')) {
            Schema::create('trade_quote_lines', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('trade_quote_id')->index();

                $table->unsignedBigInteger('trade_product_id')->nullable();

                $table->string('description', 300);

                $table->decimal('quantity', 14, 3);
                $table->string('unit', 20)->default('unit');

                $table->bigInteger('unit_price_minor');

                $table->decimal('moq_quantity', 14, 3)->nullable();
                $table->unsignedInteger('lead_time_days')->nullable();

                // Total de ligne calcule cote serveur (quantity x unit_price).
                $table->bigInteger('line_total_minor')->default(0);

                $table->unsignedInteger('position')->default(0);

                $table->timestamps();

                $table->index(['company_id', 'trade_quote_id'], 'trade_quote_lines_company_quote_idx');
            });

            DB::statement("COMMENT ON TABLE trade_quote_lines IS 'Lignes d offre commerciale Trade - prix unitaire en minor units, MOQ et delai par ligne, total de ligne calcule serveur (TRADE-004/#8479).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_quote_lines');
        Schema::dropIfExists('trade_quotes');
    }
};
