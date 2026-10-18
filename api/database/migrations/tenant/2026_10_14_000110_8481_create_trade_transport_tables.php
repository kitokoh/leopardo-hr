<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8481 (TRADE-006) — BC-36 TRADE : tables tenant `trade_transport_requests`
 * + `trade_transport_quotes`.
 *
 * DEMANDE DE COTATION TRANSPORT emise par l'acheteur ou le vendeur : origine,
 * destination, mode, description de cargaison, poids/volume/conteneurs,
 * Incoterm, date d'enlevement. Diffusee aux prestataires (transporteurs,
 * transitaires) qui repondent par une PROPOSITION STRUCTUREE :
 * `included_fees` / `excluded_fees` (jsonb), delai de transit, validite.
 *
 * Le couple inclus/exclus est essentiel : il rend explicite le PERIMETRE de
 * cout de chaque offre, condition de la comparaison honnete (§7). Une offre
 * sans perimetre documente n'est jamais presentee comme equivalente.
 *
 * Les prestataires sont des CONTREPARTIES (`trade_counterparties`) porteuses
 * des roles `carrier` / `forwarder` : l'espace prestataire authentifie reste
 * possible plus tard sans refonte (les objets sont deja orientes contrepartie).
 *
 * @see docs/specifications/VERTICAL_TRADE_OS.md §4.4
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('trade_transport_requests')) {
            Schema::create('trade_transport_requests', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('reference', 40);

                $table->unsignedBigInteger('trade_deal_id')->nullable();
                $table->unsignedBigInteger('trade_order_id')->nullable();

                $table->string('origin_country', 2)->nullable();
                $table->string('origin_city', 120)->nullable();
                $table->string('origin_port', 120)->nullable();
                $table->string('destination_country', 2)->nullable();
                $table->string('destination_city', 120)->nullable();
                $table->string('destination_port', 120)->nullable();

                $table->string('mode', 20)->default('sea');

                $table->text('cargo_description')->nullable();
                $table->decimal('weight_kg', 14, 3)->nullable();
                $table->decimal('volume_m3', 14, 3)->nullable();
                $table->json('containers')->nullable();

                $table->string('incoterm', 3)->nullable();
                $table->date('pickup_date')->nullable();

                $table->string('status', 20)->default('draft');

                $table->unsignedBigInteger('created_by_employee_id')->nullable();

                $table->timestamp('sent_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->unsignedBigInteger('selected_quote_id')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->unique(['company_id', 'reference'], 'trade_transport_requests_company_reference_unique');
                $table->index(['company_id', 'status', 'created_at'], 'trade_transport_requests_company_status_idx');
            });

            DB::statement("COMMENT ON TABLE trade_transport_requests IS 'Demandes de cotation transport Trade - origine/destination, mode, cargaison, diffusee aux prestataires contreparties (TRADE-006/#8481).';");
        }

        if (! schemaTableExists('trade_transport_quotes')) {
            Schema::create('trade_transport_quotes', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('trade_transport_request_id')->index();
                $table->unsignedBigInteger('trade_counterparty_id')->nullable();

                $table->bigInteger('price_minor');
                $table->char('currency', 3);

                // Perimetre de cout EXPLICITE : conditions d'une comparaison honnete.
                $table->json('included_fees')->nullable();
                $table->json('excluded_fees')->nullable();

                $table->unsignedInteger('transit_time_days')->nullable();
                $table->date('validity_until')->nullable();

                $table->string('status', 20)->default('received');

                $table->text('notes')->nullable();

                $table->timestamp('received_at')->nullable();
                $table->timestamp('decided_at')->nullable();

                $table->timestamps();

                $table->index(['company_id', 'status'], 'trade_transport_quotes_company_status_idx');
            });

            DB::statement("COMMENT ON TABLE trade_transport_quotes IS 'Reponses des prestataires (transporteurs/transitaires) - prix, frais inclus/exclus explicites, delai et validite (TRADE-006/#8481).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_transport_quotes');
        Schema::dropIfExists('trade_transport_requests');
    }
};
