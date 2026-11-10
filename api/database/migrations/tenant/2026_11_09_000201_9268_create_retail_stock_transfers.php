<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BC-17 RETAIL (#9268) — transferts de stock inter-emplacements.
 *
 * L'enum `RetailStockReasonCode` déclarait `transfer_in` / `transfer_out`
 * depuis #7673 sans qu'aucun service ne les produise : un commerçant
 * multi-emplacements (boutique + entrepôt) devait saisir DEUX ajustements
 * manuels, sans lien entre eux. Deux conséquences mesurables :
 *
 *  - la traçabilité était cassée (deux `adjustment` indistinguables d'une
 *    correction d'inventaire) ;
 *  - un transfert pouvait rester « à moitié fait » (sortie enregistrée,
 *    entrée oubliée) sans que rien ne le signale.
 *
 * Cette table porte l'objet métier du transfert : les deux mouvements
 * (`transfer_out` puis `transfer_in`) partagent son `id` comme
 * `reference_id`, ce qui rend la paire vérifiable en une requête.
 *
 * Migration ADDITIVE, `down()` ne détruit que ce que ce fichier a créé.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('retail_stock_transfers')) {
            return;
        }

        Schema::create('retail_stock_transfers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();

            $table->unsignedBigInteger('from_location_id');
            $table->unsignedBigInteger('to_location_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('quantity', 12, 3);

            $table->text('note')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'product_id'], 'retail_stock_transfers_company_product_idx');
            $table->index(['company_id', 'created_at'], 'retail_stock_transfers_company_created_idx');
        });

        DB::statement(
            "COMMENT ON TABLE retail_stock_transfers IS "
            ."'Transferts de stock inter-emplacements Retail - les deux mouvements "
            ."(transfer_out/transfer_in) partagent cet id comme reference_id (BC-17/#9268).'"
        );
    }

    public function down(): void
    {
        if (schemaTableExists('retail_stock_transfers')) {
            Schema::dropIfExists('retail_stock_transfers');
        }
    }
};
