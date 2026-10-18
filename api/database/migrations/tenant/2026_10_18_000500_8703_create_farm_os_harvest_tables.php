<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Farm OS — T3.2 Recoltes, entreposage et tracabilite (BC-37 AGRICULTURE, #8703).
 *
 * Invariant CENTRAL de cette migration : une meme quantite ne doit JAMAIS etre
 * comptabilisee deux fois entre recolte, stock, vente et reporting.
 *
 * Comment le schema le garantit :
 *   - `farm_harvest_lots.quantity_harvested` est la quantite RECOLTEE, ecrite
 *     UNE SEULE FOIS a la creation du lot et jamais mise a jour : c'est la
 *     reference de tracabilite ;
 *   - `farm_harvest_lots.quantity_remaining` est un CACHE derive du journal
 *     `farm_storage_movements` : il n'est jamais incremente directement par un
 *     appelant, seulement par le service, dans la meme transaction ;
 *   - `farm_storage_movements` est un JOURNAL IMMUABLE : chaque mouvement laisse
 *     exactement une ligne, avec `quantity_after` (quantite restante du lot
 *     apres le mouvement) pour un historique relisible ;
 *   - `quantity` d'un mouvement est toujours POSITIVE : le sens est porte par
 *     `movement_type` (in / transfer / loss / adjustment / sale / consumption) ;
 *   - une SORTIE (perte, vente, consommation) ne peut jamais depasser la
 *     quantite restante : on refuse (422) plutot que de laisser un stock
 *     negatif, ce qui rend impossible de vendre deux fois la meme recolte ;
 *   - `sale_document_id` / `sale_line_id` referencent le service de VENTES
 *     EXISTANT de Leopardo : Farm OS ne cree aucun document commercial et aucun
 *     systeme financier parallele ;
 *   - `client_movement_id` unique par entreprise : le rejeu d'une ecriture
 *     mobile/hors ligne renvoie le mouvement existant SANS re-appliquer la
 *     sortie de stock.
 *
 * Conventions respectees (docs/architecture + lecons CI) :
 *   - `company_id` uuid NULLABLE + indexe sur CHAQUE table (isolation tenant) ;
 *   - references internes par identifiant stable SANS cle etrangere
 *     (conventions migrations tenant §2.6) ;
 *   - `timestampTz` partout ;
 *   - `up()` idempotent (`schemaTableExists`) — Render rejoue les migrations ;
 *   - `down()` ne detruit QUE ce que ce fichier a cree (garde #8207).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('farm_storage_locations')) {
            Schema::create('farm_storage_locations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->nullable()->index();

                $table->unsignedBigInteger('farm_site_id')->nullable();
                $table->string('name', 160);
                $table->string('reference', 60)->nullable();
                // barn | silo | warehouse | cold_room | tank | field_stack | other
                // (registre FERME cote application).
                $table->string('location_type', 32)->default('other');
                // Capacite DECLAREE (NULLABLE : souvent inconnue) + unite.
                $table->decimal('capacity', 14, 3)->nullable();
                $table->string('capacity_unit', 24)->nullable();
                $table->text('notes')->nullable();
                $table->string('client_location_id', 80)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestampTz('created_at')->nullable();
                $table->timestampTz('updated_at')->nullable();

                $table->unique(['company_id', 'client_location_id'], 'farm_storage_locations_client_unique');
                $table->index(['company_id', 'name'], 'farm_storage_locations_name_index');
            });
        }

        if (! schemaTableExists('farm_harvest_lots')) {
            Schema::create('farm_harvest_lots', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->nullable()->index();

                // Tracabilite : parcelle, culture, campagne et lot.
                $table->unsignedBigInteger('parcel_id')->nullable();
                $table->unsignedBigInteger('crop_cycle_id')->nullable();
                $table->unsignedBigInteger('farm_site_id')->nullable();
                $table->unsignedBigInteger('farm_operation_id')->nullable();

                // Code de lot : identifiant de tracabilite lisible et unique par
                // entreprise (ex. « PAR-01-BLE-2026-001 »).
                $table->string('lot_code', 80);
                $table->string('crop_name', 160)->nullable();
                // Quantite RECOLTEE : ecrite une seule fois, jamais mise a jour.
                $table->decimal('quantity_harvested', 14, 3);
                $table->string('unit', 24);
                // Qualite / classement si disponible (NULLABLE).
                $table->string('quality_grade', 40)->nullable();
                // Emplacement de stockage initial (NULLABLE : peut rester au champ).
                $table->unsignedBigInteger('storage_location_id')->nullable();
                $table->timestampTz('harvested_at');
                // CACHE derive du journal des mouvements : jamais incremente
                // directement par un appelant.
                $table->decimal('quantity_remaining', 14, 3);
                // open | closed (un lot epuise est clos, jamais supprime).
                $table->string('status', 16)->default('open');
                $table->text('notes')->nullable();
                $table->string('client_harvest_id', 80)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestampTz('created_at')->nullable();
                $table->timestampTz('updated_at')->nullable();

                $table->unique(['company_id', 'lot_code'], 'farm_harvest_lots_code_unique');
                $table->unique(['company_id', 'client_harvest_id'], 'farm_harvest_lots_client_unique');
                $table->index(['company_id', 'parcel_id', 'harvested_at'], 'farm_harvest_lots_parcel_index');
                $table->index(['company_id', 'status'], 'farm_harvest_lots_status_index');
            });
        }

        if (! schemaTableExists('farm_storage_movements')) {
            Schema::create('farm_storage_movements', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->nullable()->index();

                $table->unsignedBigInteger('farm_harvest_lot_id');
                // in | transfer | loss | adjustment | sale | consumption
                // (registre FERME cote application).
                $table->string('movement_type', 24);
                // Toujours POSITIVE : le sens est porte par `movement_type`.
                $table->decimal('quantity', 14, 3);
                // Quantite restante du lot APRES le mouvement (historique relisible).
                $table->decimal('quantity_after', 14, 3);

                // Emplacement source / destination (transfert).
                $table->unsignedBigInteger('from_location_id')->nullable();
                $table->unsignedBigInteger('to_location_id')->nullable();

                // Contrepartie commerciale : reference vers le service de VENTES
                // EXISTANT. Farm OS ne cree ni facture ni document commercial.
                $table->unsignedBigInteger('sale_document_id')->nullable();
                $table->unsignedBigInteger('sale_line_id')->nullable();
                $table->unsignedBigInteger('counterparty_id')->nullable();
                $table->decimal('unit_price', 14, 2)->nullable();
                $table->char('currency', 3)->nullable();

                $table->string('reason', 200)->nullable();
                $table->unsignedBigInteger('recorded_by')->nullable();
                // Idempotence terrain : un rejeu ne re-applique pas la sortie.
                $table->string('client_movement_id', 80)->nullable();
                $table->timestampTz('occurred_at');
                $table->timestampTz('created_at')->nullable();
                $table->timestampTz('updated_at')->nullable();

                $table->unique(['company_id', 'client_movement_id'], 'farm_storage_movements_client_unique');
                $table->index(['company_id', 'farm_harvest_lot_id', 'occurred_at'], 'farm_storage_movements_lot_index');
                $table->index(['company_id', 'sale_document_id'], 'farm_storage_movements_sale_index');
            });
        }
    }

    public function down(): void
    {
        // Ordre inverse de creation : les mouvements referencent les lots.
        Schema::dropIfExists('farm_storage_movements');
        Schema::dropIfExists('farm_harvest_lots');
        Schema::dropIfExists('farm_storage_locations');
    }
};
