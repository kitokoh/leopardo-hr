<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Farm OS — T2.1 Intrants et stocks agricoles (BC-37 AGRICULTURE, #8554).
 *
 * Catalogue d'intrants (semences, engrais, produits phytosanitaires, aliments
 * pour animaux, pièces détachées…), lots avec date de péremption, et journal
 * IMMUABLE des mouvements (entrée / sortie / ajustement).
 *
 * Invariants portés par le schéma :
 *   - `farm_input_stock_movements` est un JOURNAL : chaque changement de
 *     quantité d'un lot y laisse exactement une ligne. On ne met jamais à jour
 *     une ligne de mouvement — on en ajoute une (traçabilité, exigence de la
 *     mission) ;
 *   - `quantity_after` est stocké pour rendre l'historique relisible sans
 *     rejouer tous les mouvements ;
 *   - `expiry_date` NULLABLE : tous les intrants ne périment pas (pièces
 *     détachées) — Farm OS n'invente jamais une date ;
 *   - `alert_threshold` NULLABLE : pas de seuil ⇒ pas d'alerte, jamais un
 *     seuil par défaut arbitraire.
 *
 * Conventions respectées (docs/architecture + leçons CI) :
 *   - `company_id` uuid NULLABLE + indexé sur CHAQUE table (isolation tenant) ;
 *   - références internes par identifiant stable SANS clé étrangère
 *     (conventions migrations tenant §2.6) ;
 *   - `timestampTz` partout ;
 *   - `up()` idempotent (`schemaTableExists`) — Render rejoue les migrations ;
 *   - `down()` ne détruit QUE ce que ce fichier a créé (garde #8207).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('farm_inputs')) {
            Schema::create('farm_inputs', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->nullable()->index();

                $table->string('name', 160);
                $table->string('reference', 60)->nullable();
                // Catégorie d'intrant : semence, engrais, phyto, aliment, pièce…
                // Registre FERMÉ côté application (enum FarmInputCategory).
                $table->string('category', 40);
                // Unité de gestion : kg, L, unité, sac, dose… (enum FarmInputUnit).
                $table->string('unit', 24);
                // Conditionnement (ex. « sac de 50 kg ») — informatif, jamais
                // utilisé pour convertir automatiquement une quantité.
                $table->string('packaging', 80)->nullable();
                // Seuil d'alerte de réapprovisionnement, dans l'unité ci-dessus.
                $table->decimal('alert_threshold', 14, 3)->nullable();
                // Produit réglementé / dangereux : conditionne les permissions
                // et l'affichage des précautions (aucun conseil de dosage).
                $table->boolean('is_regulated')->default(false);
                $table->text('notes')->nullable();
                $table->timestampTz('created_at')->nullable();
                $table->timestampTz('updated_at')->nullable();

                $table->unique(['company_id', 'name'], 'farm_inputs_company_name_unique');
                $table->index(['company_id', 'category'], 'farm_inputs_company_category_index');
            });
        }

        if (! schemaTableExists('farm_input_lots')) {
            Schema::create('farm_input_lots', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->nullable()->index();

                $table->unsignedBigInteger('farm_input_id');
                $table->string('lot_number', 80);
                // NULLABLE : tous les intrants ne périment pas.
                $table->date('expiry_date')->nullable();
                // Quantité courante du lot, dans l'unité de l'intrant.
                $table->decimal('quantity', 14, 3)->default(0);
                $table->unsignedBigInteger('supplier_id')->nullable();
                $table->timestampTz('received_at')->nullable();
                $table->timestampTz('created_at')->nullable();
                $table->timestampTz('updated_at')->nullable();

                $table->unique(['company_id', 'farm_input_id', 'lot_number'], 'farm_input_lots_unique');
                $table->index(['company_id', 'expiry_date'], 'farm_input_lots_expiry_index');
            });
        }

        if (! schemaTableExists('farm_input_stock_movements')) {
            Schema::create('farm_input_stock_movements', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->nullable()->index();

                $table->unsignedBigInteger('farm_input_id');
                $table->unsignedBigInteger('farm_input_lot_id')->nullable();
                // receipt | issue | adjustment (enum FarmStockMovementType).
                $table->string('movement_type', 24);
                // Toujours POSITIVE : le sens est porté par `movement_type`.
                // Un ajustement à la baisse est un `adjustment` avec un
                // `quantity_after` inférieur — jamais une quantité négative.
                $table->decimal('quantity', 14, 3);
                // Quantité du lot APRÈS le mouvement : historique relisible.
                $table->decimal('quantity_after', 14, 3);
                // Rattachement optionnel à une opération agricole (consommation
                // d'intrant sur une intervention) — jamais une double saisie.
                $table->unsignedBigInteger('farm_operation_id')->nullable();
                $table->unsignedBigInteger('parcel_id')->nullable();
                $table->string('reason', 200)->nullable();
                $table->unsignedBigInteger('recorded_by')->nullable();
                // Idempotence terrain : le rejeu d'une écriture mobile/hors ligne
                // renvoie le mouvement existant au lieu d'en créer un second.
                $table->string('client_movement_id', 80)->nullable();
                $table->timestampTz('occurred_at');
                $table->timestampTz('created_at')->nullable();
                $table->timestampTz('updated_at')->nullable();

                $table->unique(['company_id', 'client_movement_id'], 'farm_input_movements_client_unique');
                $table->index(['company_id', 'farm_input_id', 'occurred_at'], 'farm_input_movements_input_index');
            });
        }
    }

    public function down(): void
    {
        // Ordre inverse de création : les mouvements référencent les lots, qui
        // référencent les intrants.
        Schema::dropIfExists('farm_input_stock_movements');
        Schema::dropIfExists('farm_input_lots');
        Schema::dropIfExists('farm_inputs');
    }
};
