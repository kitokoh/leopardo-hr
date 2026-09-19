<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #7800 (PHARMA-003) — stock d'officine par lots avec péremption et journal
 * de mouvements immuable.
 *
 * - `pharmacy_batches` : lot reçu (n° de lot, date de péremption, quantité
 *   restante, coût unitaire, fournisseur optionnel). Unicité par tenant du
 *   couple (produit, n° de lot) — le même lot reçu deux fois s'incrémente.
 * - `pharmacy_stock_movements` : journal APPEND-ONLY — delta signé, type
 *   contrôlé (receipt|sale|adjustment|expiry_writeoff|return), raison,
 *   référence polymorphe (commande d'achat, vente…), employé auteur.
 *   Aucune quantité de lot ne change sans mouvement (traçabilité
 *   réglementaire) ; les mouvements ne sont JAMAIS modifiés ni supprimés
 *   (garde applicative dans le modèle).
 *
 * Tenant-scoped, sans FK (colonnes simples + index nommés, conventions
 * migrations tenant §2.6 — pattern Retail #7673). Idempotente + down()
 * complet avec gardes. Quantités en entiers (unités de délivrance),
 * montants en decimal — jamais de float.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('pharmacy_batches')) {
            Schema::create('pharmacy_batches', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('supplier_id')->nullable();
                $table->string('batch_number', 64);
                $table->date('expiry_date');
                $table->unsignedInteger('quantity')->default(0);
                $table->decimal('unit_cost', 12, 2)->nullable();
                $table->timestamp('received_at')->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'product_id', 'batch_number'], 'pharmacy_batches_company_product_batch_unique');
                $table->index(['company_id', 'product_id', 'expiry_date'], 'pharmacy_batches_company_product_expiry_idx');
                $table->index(['company_id', 'expiry_date'], 'pharmacy_batches_company_expiry_idx');
            });

            DB::statement("COMMENT ON TABLE pharmacy_batches IS 'Lots de stock d''officine - numero de lot unique par (tenant, produit), peremption, quantite restante (PHARMA-003/#7800).';");
        }

        if (! schemaTableExists('pharmacy_stock_movements')) {
            Schema::create('pharmacy_stock_movements', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('batch_id');
                $table->string('type', 30); // receipt|sale|adjustment|expiry_writeoff|return
                $table->integer('quantity_delta');
                $table->string('reason', 500)->nullable();
                $table->string('reference_type', 80)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->unsignedBigInteger('created_by_employee_id')->nullable();

                $table->timestamps();

                $table->index(['company_id', 'product_id'], 'pharmacy_stock_movements_company_product_idx');
                $table->index(['company_id', 'batch_id'], 'pharmacy_stock_movements_company_batch_idx');
                $table->index(['company_id', 'reference_type', 'reference_id'], 'pharmacy_stock_movements_company_reference_idx');
            });

            DB::statement("COMMENT ON TABLE pharmacy_stock_movements IS 'Journal immuable append-only des mouvements de stock d''officine - delta signe, type controle, reference polymorphe (PHARMA-003/#7800).';");
        }
    }

    public function down(): void
    {
        if (schemaTableExists('pharmacy_stock_movements')) {
            Schema::dropIfExists('pharmacy_stock_movements');
        }

        if (schemaTableExists('pharmacy_batches')) {
            Schema::dropIfExists('pharmacy_batches');
        }
    }
};
