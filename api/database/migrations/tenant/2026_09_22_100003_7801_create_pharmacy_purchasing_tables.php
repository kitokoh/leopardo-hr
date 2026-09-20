<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #7801 (PHARMA-004) — fournisseurs et commandes d'achat d'officine.
 *
 * `pharmacy_suppliers` : grossistes-répartiteurs, laboratoires.
 * `pharmacy_purchase_orders` : cycle draft → ordered → partially_received →
 * received | cancelled ; numéro `PO-YYYY-XXXX` séquencé PAR TENANT
 * (unique company_id + number).
 * `pharmacy_purchase_order_lines` : quantités commandées/reçues, prix
 * unitaire (decimal — jamais de float). Sur-réception impossible (CHECK).
 *
 * Gardes F-17 : schemaTableExists(), migration additive et idempotente.
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
                $table->string('type', 30)->default('wholesaler'); // wholesaler|laboratory|other
                $table->string('contact_name', 191)->nullable();
                $table->string('phone', 50)->nullable();
                $table->string('email', 191)->nullable();
                $table->string('address', 255)->nullable();
                $table->string('status', 20)->default('active'); // active|archived
                $table->timestamps();

                $table->index(['company_id', 'status'], 'pharmacy_suppliers_company_status_idx');
                $table->index(['company_id', 'name'], 'pharmacy_suppliers_company_name_idx');
            });
        }

        if (! schemaTableExists('pharmacy_purchase_orders')) {
            Schema::create('pharmacy_purchase_orders', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('supplier_id');
                $table->string('number', 20);
                // draft|ordered|partially_received|received|cancelled
                $table->string('status', 30)->default('draft');
                $table->timestamp('ordered_at')->nullable();
                $table->timestamp('received_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->string('notes', 500)->nullable();
                $table->unsignedBigInteger('created_by_employee_id')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'number'], 'pharmacy_po_company_number_unique');
                $table->index(['company_id', 'status'], 'pharmacy_po_company_status_idx');
                $table->index(['company_id', 'supplier_id'], 'pharmacy_po_company_supplier_idx');
            });

            $schema = resolveTableSchema('pharmacy_purchase_orders');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'pharmacy_purchase_orders_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"pharmacy_purchase_orders\" ADD CONSTRAINT pharmacy_purchase_orders_status_check "
                    ."CHECK (status IN ('draft','ordered','partially_received','received','cancelled')); END IF; END $$"
                );
            }
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

            $schema = resolveTableSchema('pharmacy_purchase_order_lines');
            if ($schema !== null) {
                // Sur-réception structurellement impossible (défense en
                // profondeur sous la garde applicative).
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'pharmacy_po_lines_received_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"pharmacy_purchase_order_lines\" ADD CONSTRAINT pharmacy_po_lines_received_check "
                    .'CHECK (quantity_received <= quantity_ordered); END IF; END $$'
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacy_purchase_order_lines');
        Schema::dropIfExists('pharmacy_purchase_orders');
        Schema::dropIfExists('pharmacy_suppliers');
    }
};
