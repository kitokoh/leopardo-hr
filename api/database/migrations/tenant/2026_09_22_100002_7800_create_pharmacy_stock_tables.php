<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #7800 (PHARMA-003) — stock d'officine par lots + journal immuable.
 *
 * `pharmacy_batches` : lots physiques (n° de lot, péremption, quantité
 * restante, coût unitaire). Unicité (company_id, product_id, batch_number).
 *
 * `pharmacy_stock_movements` : journal append-only de TOUT changement de
 * stock (receipt|sale|adjustment|expiry_writeoff|return, quantity_delta
 * signé). Aucun UPDATE/DELETE applicatif (garde modèle) — traçabilité
 * réglementaire de l'officine (ordonnancier PHARMA-006 dérivé).
 *
 * Gardes F-17 : schemaTableExists(), migration additive et idempotente.
 * Montants en decimal — jamais de float.
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
                $table->string('batch_number', 64);
                $table->date('expiry_date');
                $table->integer('quantity')->default(0);
                $table->decimal('unit_cost', 12, 2)->default(0);
                $table->unsignedBigInteger('supplier_id')->nullable();
                $table->timestamp('received_at')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'product_id', 'batch_number'], 'pharmacy_batches_company_product_batch_unique');
                $table->index(['company_id', 'product_id', 'expiry_date'], 'pharmacy_batches_company_product_expiry_idx');
                $table->index(['company_id', 'expiry_date'], 'pharmacy_batches_company_expiry_idx');
            });

            $schema = resolveTableSchema('pharmacy_batches');
            if ($schema !== null) {
                // Jamais de lot négatif (défense en profondeur sous le service).
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'pharmacy_batches_quantity_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"pharmacy_batches\" ADD CONSTRAINT pharmacy_batches_quantity_check "
                    .'CHECK (quantity >= 0); END IF; END $$'
                );
            }
        }

        if (! schemaTableExists('pharmacy_stock_movements')) {
            Schema::create('pharmacy_stock_movements', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('batch_id')->nullable();
                // receipt | sale | adjustment | expiry_writeoff | return
                $table->string('type', 30);
                $table->integer('quantity_delta');
                $table->string('reason', 255)->nullable();
                $table->string('reference_type', 100)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->unsignedBigInteger('created_by_employee_id')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['company_id', 'product_id', 'created_at'], 'pharmacy_movements_company_product_created_idx');
                $table->index(['company_id', 'type', 'created_at'], 'pharmacy_movements_company_type_created_idx');
                $table->index(['company_id', 'batch_id'], 'pharmacy_movements_company_batch_idx');
            });

            $schema = resolveTableSchema('pharmacy_stock_movements');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'pharmacy_stock_movements_type_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"pharmacy_stock_movements\" ADD CONSTRAINT pharmacy_stock_movements_type_check "
                    ."CHECK (type IN ('receipt','sale','adjustment','expiry_writeoff','return')); END IF; END $$"
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacy_stock_movements');
        Schema::dropIfExists('pharmacy_batches');
    }
};
