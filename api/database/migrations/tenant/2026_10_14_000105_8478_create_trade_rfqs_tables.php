<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8478 (TRADE-003) — BC-36 TRADE : tables tenant `trade_rfqs` + `trade_rfq_lines`.
 *
 * DEMANDE DE PRIX (RFQ) emise par l'acheteur : produit/specifications,
 * quantites, destination, Incoterm, budget et echeance. Diffusee a plusieurs
 * fournisseurs (invitations), elle recolte des offres comparables.
 *
 * `reference` : `RFQ-YYYY-NNNNNN`, unique par tenant.
 * Etats : `draft|open|closed|awarded|cancelled` (machine a etats
 * TradeRfqStateMachine) ; toute transition illégale = 422.
 *
 * `trade_rfq_lines.trade_product_id` est nullable : la saisie LIBRE d'une
 * ligne (produit non encore catalogue) est permise — un acheteur ne doit
 * jamais etre bloque par l'obligation de creer un produit d'abord.
 *
 * @see docs/specifications/VERTICAL_TRADE_OS.md §4.3
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('trade_rfqs')) {
            Schema::create('trade_rfqs', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('reference', 40);
                $table->unsignedBigInteger('trade_deal_id')->nullable();

                $table->string('title', 200);
                $table->string('status', 20)->default('draft');

                $table->char('destination_country', 2)->nullable();
                $table->string('destination_port', 120)->nullable();
                $table->string('incoterm', 3)->nullable();

                $table->bigInteger('budget_minor')->nullable();
                $table->char('budget_currency', 3)->nullable();

                $table->timestamp('deadline_at')->nullable();
                $table->text('notes')->nullable();

                $table->unsignedBigInteger('created_by_employee_id')->nullable();

                $table->timestamp('published_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamp('awarded_at')->nullable();
                $table->unsignedBigInteger('awarded_quote_id')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->unique(['company_id', 'reference'], 'trade_rfqs_company_reference_unique');
                $table->index(['company_id', 'status', 'created_at'], 'trade_rfqs_company_status_date_idx');
                $table->index(['company_id', 'trade_deal_id'], 'trade_rfqs_company_deal_idx');
            });

            DB::statement("COMMENT ON TABLE trade_rfqs IS 'Demandes de prix (RFQ) Trade - reference RFQ unique par tenant, cycle draft/open/closed/awarded/cancelled, ligne libre autorisee (TRADE-003/#8478).';");
        }

        if (! schemaTableExists('trade_rfq_lines')) {
            Schema::create('trade_rfq_lines', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('trade_rfq_id')->index();

                $table->unsignedBigInteger('trade_product_id')->nullable();

                $table->string('description', 300);
                $table->json('specs')->nullable();

                $table->decimal('quantity', 14, 3);
                $table->string('unit', 20)->default('unit');

                $table->bigInteger('target_unit_price_minor')->nullable();

                $table->unsignedInteger('position')->default(0);

                $table->timestamps();

                $table->index(['company_id', 'trade_rfq_id'], 'trade_rfq_lines_company_rfq_idx');
            });

            DB::statement("COMMENT ON TABLE trade_rfq_lines IS 'Lignes de RFQ Trade - produit catalogue OU saisie libre, quantite decimale, prix cible indicatif (TRADE-003/#8478).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_rfq_lines');
        Schema::dropIfExists('trade_rfqs');
    }
};
