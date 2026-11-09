<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #9208 / #9212 (BC-17 RETAIL) — remboursements en caisse et mouvements de
 * tiroir-caisse du POS Retail.
 *
 * - `retail_pos_refunds` : remboursement d'une commande encaissee (total ou
 *   partiel), avec motif, auteur et cle d'idempotence. Avant cette table, le
 *   POS ne savait QUE annuler une commande (`cancelOrder`) : le stock etait
 *   restaure mais l'argent restait encaisse — un commercant ne pouvait pas
 *   rendre 5 EUR a un client qui rapporte un article.
 * - `retail_cash_movements` : entree / sortie / prelevement d'especes sur une
 *   session ouverte. Sans cette table, l'ecart de cloture etait
 *   inexplicable (un prelevement pour la banque ressemblait a un vol).
 *
 * Tenant-scoped, sans FK (colonnes simples + index nommes, conventions
 * migrations tenant §2.6 — pattern #7674). Idempotente + down() complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('retail_pos_refunds')) {
            Schema::create('retail_pos_refunds', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('pos_session_id')->nullable();
                $table->string('method', 20);
                $table->unsignedBigInteger('amount_minor');
                $table->char('currency', 3)->default('XOF');
                $table->string('reason', 255);
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamp('refunded_at')->nullable();
                $table->string('idempotency_key', 64)->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'idempotency_key'], 'retail_pos_refunds_company_idempotency_key_unique');
                $table->index(['company_id', 'order_id'], 'retail_pos_refunds_company_order_idx');
                $table->index(['company_id', 'pos_session_id'], 'retail_pos_refunds_company_session_idx');
            });

            DB::statement("COMMENT ON TABLE retail_pos_refunds IS 'Remboursements POS Retail - total ou partiel, motif obligatoire, idempotency_key unique par tenant (BC-17/#9208).';");
        }

        if (! schemaTableExists('retail_cash_movements')) {
            Schema::create('retail_cash_movements', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('pos_session_id');
                $table->string('type', 10);
                $table->unsignedBigInteger('amount_minor');
                $table->string('reason', 255);
                $table->unsignedBigInteger('user_id')->nullable();

                $table->timestamps();

                $table->index(['company_id', 'pos_session_id'], 'retail_cash_movements_company_session_idx');
            });

            DB::statement("COMMENT ON TABLE retail_cash_movements IS 'Mouvements de tiroir-caisse POS Retail - type in|out|drop, motif obligatoire (BC-17/#9212).';");
        }
    }

    public function down(): void
    {
        if (schemaTableExists('retail_cash_movements')) {
            Schema::dropIfExists('retail_cash_movements');
        }

        if (schemaTableExists('retail_pos_refunds')) {
            Schema::dropIfExists('retail_pos_refunds');
        }
    }
};
