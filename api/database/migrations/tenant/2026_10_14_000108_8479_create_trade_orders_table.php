<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8479 (TRADE-004) — BC-36 TRADE : table tenant `trade_orders`.
 *
 * COMMANDE issue de l'acceptation d'une offre (`trade_quote_id`) — ou saisie
 * directement. `direction` : `purchase|sale` (commande d'achat cote
 * importateur/grossiste, commande de vente cote exportateur/fournisseur) — le
 * meme objet, deux sens.
 *
 * `total_minor` FIGE serveur. Cycle de vie :
 * `draft → confirmed → preparing → ready → shipped → delivered → closed`,
 * annulation permise avant `shipped` (TradeOrderStateMachine).
 *
 * @see docs/specifications/VERTICAL_TRADE_OS.md §4.3
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('trade_orders')) {
            Schema::create('trade_orders', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('reference', 40);
                $table->string('direction', 20);

                $table->unsignedBigInteger('trade_deal_id')->nullable();
                $table->unsignedBigInteger('trade_quote_id')->nullable();
                $table->unsignedBigInteger('trade_counterparty_id')->nullable();

                $table->string('status', 20)->default('draft');

                $table->char('currency', 3);
                $table->string('incoterm', 3)->nullable();

                $table->bigInteger('total_minor')->default(0);

                $table->string('payment_terms', 200)->nullable();

                $table->timestamp('ordered_at')->nullable();
                $table->timestamp('expected_delivery_at')->nullable();
                $table->timestamp('shipped_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->string('cancel_reason', 200)->nullable();

                $table->text('notes')->nullable();

                $table->unsignedBigInteger('created_by_employee_id')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->unique(['company_id', 'reference'], 'trade_orders_company_reference_unique');
                $table->index(['company_id', 'status', 'created_at'], 'trade_orders_company_status_date_idx');
                $table->index(['company_id', 'trade_deal_id'], 'trade_orders_company_deal_idx');
            });

            DB::statement("COMMENT ON TABLE trade_orders IS 'Commandes Trade (achat/vente) - issues d une offre acceptee, total fige serveur, cycle confirme/prepare/pret/expedie/livre/cloture (TRADE-004/#8479).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_orders');
    }
};
