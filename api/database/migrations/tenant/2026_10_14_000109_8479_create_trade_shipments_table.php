<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8479 (TRADE-004) — BC-36 TRADE : table tenant `trade_shipments`.
 *
 * EXPEDITION d'une commande : mode (`sea|air|road|rail|multimodal`),
 * transporteur, reference de suivi, statut
 * (`pending|booked|in_transit|arrived|in_customs|delivered|exception`).
 *
 * En v1 la reference de suivi est SAISIE (ou capturee d'une reponse
 * prestataire) : aucun adaptateur de tracking externe n'etant souscrit, le
 * systeme n'invente jamais de position ni d'ETA.
 *
 * @see docs/specifications/VERTICAL_TRADE_OS.md §4.3
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('trade_shipments')) {
            Schema::create('trade_shipments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('trade_order_id')->index();

                $table->string('mode', 20)->default('sea');
                $table->string('carrier_name', 200)->nullable();
                $table->string('tracking_reference', 120)->nullable();

                $table->string('status', 20)->default('pending');

                $table->string('origin', 200)->nullable();
                $table->string('destination', 200)->nullable();

                $table->timestamp('eta')->nullable();
                $table->timestamp('shipped_at')->nullable();
                $table->timestamp('arrived_at')->nullable();
                $table->timestamp('delivered_at')->nullable();

                $table->text('notes')->nullable();

                $table->timestamps();

                $table->index(['company_id', 'status'], 'trade_shipments_company_status_idx');
            });

            DB::statement("COMMENT ON TABLE trade_shipments IS 'Expeditions Trade - mode, transporteur, reference de suivi saisie (aucun tracking externe invente en v1), statut douanier inclus (TRADE-004/#8479).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_shipments');
    }
};
