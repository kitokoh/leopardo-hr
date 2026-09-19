<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #7801 (PHARMA-004) — fournisseurs et commandes d'achat de l'officine.
 *
 * - `pharmacy_suppliers` : grossistes-répartiteurs, laboratoires et autres
 *   fournisseurs du tenant (contact, statut active|archived).
 * - `pharmacy_purchase_orders` : commandes d'achat, numéro `PO-YYYY-XXXX`
 *   séquencé PAR TENANT (unique (company_id, number)), cycle
 *   draft → ordered → partially_received → received | cancelled.
 * - `pharmacy_purchase_order_lines` : lignes (produit, quantité commandée,
 *   quantité reçue, prix unitaire decimal). La réception délègue à
 *   PharmacyStockService::receive() → lots + mouvements `receipt`
 *   (#7800), sur-réception refusée.
 *
 * Tenant-scoped, sans FK (colonnes simples + index nommés, conventions
 * migrations tenant §2.6). Idempotente + down() complet avec gardes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('pharmacy_suppliers')) {
            Schema::create('pharmacy_suppliers', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('name', 191);
                $table->string('type', 20)->default('wholesaler'); // wholesaler|laboratory|other
                $table->string('contact_name', 191)->nullable();
                $table->string('phone', 50)->nullable();
                $table->string('email', 191)->nullable();
                $table->string('address', 500)->nullable();
                $table->string('status', 20)->default('active'); // active|archived

                $table->timestamps();

                $table->index(['company_id', 'status'], 'pharmacy_suppliers_company_status_idx');
                $table->index(['company_id', 'type'], 'pharmacy_suppliers_company_type_idx');
            });

            DB::statement("COMMENT ON TABLE pharmacy_suppliers IS 'Fournisseurs d''officine - grossistes-repartiteurs, laboratoires (PHARMA-004/#7801).';");
        }

        if (! schemaTableExists('pharmacy_purchase_orders')) {
            Schema::create('pharmacy_purchase_orders', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('supplier_id');
                $table->string('number', 20);
                $table->string('status', 30)->default('draft'); // draft|ordered|partially_received|received|cancelled
                $table->timestamp('ordered_at')->nullable();
                $table->timestamp('received_at')->nullable();
                $table->string('notes', 500)->nullable();
                $table->unsignedBigInteger('created_by_employee_id')->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'number'], 'pharmacy_purchase_orders_company_number_unique');
                $table->index(['company_id', 'status'], 'pharmacy_purchase_orders_company_status_idx');
                $table->index(['company_id', 'supplier_id'], 'pharmacy_purchase_orders_company_supplier_idx');
            });

            DB::statement("COMMENT ON TABLE pharmacy_purchase_orders IS 'Commandes d''achat d''officine - numero PO-YYYY-XXXX sequence par tenant, cycle draft->ordered->partially_received->received|cancelled (PHARMA-004/#7801).';");
        }

        if (! schemaTableExists('pharmacy_purchase_order_lines')) {
            Schema::create('pharmacy_purchase_order_lines', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('purchase_order_id');
                $table->unsignedBigInteger('product_id');
                $table->unsignedInteger('quantity_ordered');
                $table->unsignedInteger('quantity_received')->default(0);
                $table->decimal('unit_price', 12, 2)->default(0);

                $table->timestamps();

                $table->index(['company_id', 'purchase_order_id'], 'pharmacy_po_lines_company_po_idx');
                $table->index(['company_id', 'product_id'], 'pharmacy_po_lines_company_product_idx');
            });

            DB::statement("COMMENT ON TABLE pharmacy_purchase_order_lines IS 'Lignes de commande d''achat d''officine - quantite recue jamais superieure a la commandee (PHARMA-004/#7801).';");
        }
    }

    public function down(): void
    {
        if (schemaTableExists('pharmacy_purchase_order_lines')) {
            Schema::dropIfExists('pharmacy_purchase_order_lines');
        }

        if (schemaTableExists('pharmacy_purchase_orders')) {
            Schema::dropIfExists('pharmacy_purchase_orders');
        }

        if (schemaTableExists('pharmacy_suppliers')) {
            Schema::dropIfExists('pharmacy_suppliers');
        }
    }
};
