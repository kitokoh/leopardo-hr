<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8480 (TRADE-005) — BC-36 TRADE : table tenant `trade_deals`.
 *
 * DOSSIER DE TRANSACTION — conteneur metier qui rassemble RFQ, offres,
 * commandes, transport, couts, documents et messages d'une meme affaire.
 * C'est l'objet qui porte la conversation (« un espace rattache a la
 * transaction ») et la timeline d'evenements.
 *
 * `direction` : `import|export|domestic_purchase|domestic_sale` — le sens
 * commercial du dossier, jamais un role utilisateur (une entreprise peut mener
 * des dossiers des deux sens).
 *
 * `reference` : `TRD-YYYY-NNNNNN`, unique par tenant (jamais globale : deux
 * tenants peuvent avoir la meme reference sans collision).
 *
 * @see docs/specifications/VERTICAL_TRADE_OS.md §4.3
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('trade_deals')) {
            Schema::create('trade_deals', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('reference', 40);
                $table->string('direction', 30);

                $table->unsignedBigInteger('trade_counterparty_id')->nullable();

                $table->string('title', 200);
                $table->string('status', 20)->default('open');
                $table->char('currency', 3);

                $table->unsignedBigInteger('opened_by_employee_id')->nullable();

                $table->json('metadata')->nullable();

                $table->timestamp('closed_at')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->unique(['company_id', 'reference'], 'trade_deals_company_reference_unique');
                $table->index(['company_id', 'status', 'created_at'], 'trade_deals_company_status_date_idx');
                $table->index(['company_id', 'direction'], 'trade_deals_company_direction_idx');
            });

            DB::statement("COMMENT ON TABLE trade_deals IS 'Dossiers de transaction Trade - conteneur RFQ/offres/commandes/transport/couts/documents, reference TRD unique par tenant (TRADE-005/#8480).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_deals');
    }
};
