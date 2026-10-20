<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8482 (TRADE-007) — BC-36 TRADE : tables tenant `trade_cost_estimates`
 * + `trade_cost_estimate_lines`.
 *
 * CALCUL DU COUT TOTAL RENDU : prix d'achat, transport interieur origine,
 * fret international, assurance, manutention, frais portuaires, droits de
 * douane, taxes, frais de dedouanement, transport interieur destination,
 * autres frais connus.
 *
 * CHAQUE POSTE porte sa PROVENANCE dans `source` :
 * `confirmed|estimated|user_input|unavailable`. C'est la garantie
 * d'honnetete du calcul — l'utilisateur voit ce qui est chiffre, ce qui est
 * estime et ce qui n'est PAS connu ; une ligne `unavailable` est listee mais
 * EXCLUE du total (jamais comblee par une valeur inventee).
 *
 * FX TRACE : `fx_rate` + `fx_rate_date` + `fx_source` (`manual` ou fournisseur
 * configure). Aucun taux n'est devine — si le taux est inconnu, les conversions
 * restent nulles et la ligne est marquee non convertie.
 *
 * `total_minor` est calcule par `TradeLandedCostCalculator` (service pur,
 * deterministe, testable en unite — math entiere en minor units).
 *
 * @see docs/specifications/VERTICAL_TRADE_OS.md §4.5 et §7
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('trade_cost_estimates')) {
            Schema::create('trade_cost_estimates', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('reference', 40);

                $table->unsignedBigInteger('trade_deal_id')->nullable();
                $table->unsignedBigInteger('trade_quote_id')->nullable();
                $table->unsignedBigInteger('trade_order_id')->nullable();

                $table->string('origin_country', 2)->nullable();
                $table->string('destination_country', 2)->nullable();
                $table->string('incoterm', 3)->nullable();

                $table->bigInteger('goods_value_minor')->default(0);
                $table->char('goods_currency', 3);
                $table->char('display_currency', 3);

                // Taux de change TRACE : jamais invente. decimal(18,8) — precision FX.
                $table->decimal('fx_rate', 18, 8)->nullable();
                $table->date('fx_rate_date')->nullable();
                $table->string('fx_source', 40)->nullable();

                $table->bigInteger('total_minor')->default(0);
                $table->string('status', 20)->default('draft');

                $table->text('notes')->nullable();

                $table->unsignedBigInteger('created_by_employee_id')->nullable();
                $table->timestamp('finalized_at')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->unique(['company_id', 'reference'], 'trade_cost_estimates_company_reference_unique');
                $table->index(['company_id', 'status'], 'trade_cost_estimates_company_status_idx');
            });

            DB::statement("COMMENT ON TABLE trade_cost_estimates IS 'Calculs de cout total rendu Trade - provenance tracee par poste (confirme/estime/saisi/indisponible), taux de change trace, total calcule server-side (TRADE-007/#8482).';");
        }

        if (! schemaTableExists('trade_cost_estimate_lines')) {
            Schema::create('trade_cost_estimate_lines', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('trade_cost_estimate_id')->index();

                $table->string('type', 40);
                $table->string('label', 200)->nullable();

                $table->bigInteger('amount_minor');
                $table->char('currency', 3);
                $table->bigInteger('converted_amount_minor')->nullable();

                // Provenance du chiffre : pilier de l'honnetete du calcul.
                $table->string('source', 20)->default('user_input');

                $table->string('note', 300)->nullable();
                $table->unsignedInteger('position')->default(0);

                $table->timestamps();

                $table->index(['company_id', 'trade_cost_estimate_id'], 'trade_cost_estimate_lines_company_estimate_idx');
            });

            DB::statement("COMMENT ON TABLE trade_cost_estimate_lines IS 'Postes de cout Trade - type normalise, montant en minor units, provenance obligatoire, conversion nullable si FX indisponible (TRADE-007/#8482).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_cost_estimate_lines');
        Schema::dropIfExists('trade_cost_estimates');
    }
};
