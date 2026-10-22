<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Farm OS — T3.1 Elevage (BC-37 AGRICULTURE, #8702).
 *
 * Registre du cheptel : lots (identification par lot) et animaux (identification
 * individuelle), mouvements d'effectif, sante animale, reproduction, production,
 * alimentation et stocks d'aliments / produits veterinaires.
 *
 * Invariants portes par le schema :
 *   - `head_count` est l'effectif PRESENT du lot, tenu par le journal
 *     `farm_livestock_events` : une sortie ne peut jamais rendre l'effectif
 *     negatif (garde applicative + verrouillage), ce qui empeche qu'une meme
 *     tete soit comptee deux fois (vente + mortalite) ;
 *   - `farm_livestock_events` est un JOURNAL : chaque mouvement d'effectif y
 *     laisse exactement une ligne, jamais une mise a jour ;
 *   - `quantity` d'un evenement est toujours POSITIVE : le sens est porte par
 *     `event_type`, jamais par le signe ;
 *   - `expected_due_at` (reproduction) est une date DECLAREE ou calculee depuis
 *     une duree de gestation SAISIE : Farm OS ne connait pas la duree de
 *     gestation d'une espece ;
 *   - `withdrawal_until` (sante) est STOCKE tel que declare : Farm OS ne
 *     calcule ni ne prescrit aucun delai d'attente ;
 *   - `cost_amount` / `unit_price` NULLABLES : un cout inconnu reste inconnu.
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
        if (! schemaTableExists('farm_livestock_batches')) {
            Schema::create('farm_livestock_batches', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->nullable()->index();

                $table->unsignedBigInteger('farm_site_id')->nullable();
                $table->string('name', 160);
                $table->string('reference', 60)->nullable();
                // cattle | sheep | goat | poultry | pig | bee | fish | rabbit |
                // horse | camel | other (registre FERME : enum FarmSpecies).
                $table->string('species', 24);
                $table->string('breed', 80)->nullable();
                // male | female | unknown (enum FarmAnimalSex). `unknown` est une
                // valeur de plein droit : sur un lot, le sexe est souvent inconnu.
                $table->string('sex', 16)->default('unknown');
                // Identification par lot : effectif PRESENT, tenu par le journal.
                $table->unsignedInteger('head_count')->default(0);
                // Emplacement / batiment (texte libre : aucun referentiel impose).
                $table->string('location', 160)->nullable();
                $table->date('started_on')->nullable();
                // active | sold | dead | transferred | culled (enum FarmAnimalStatus).
                $table->string('status', 16)->default('active');
                $table->text('notes')->nullable();
                $table->string('client_batch_id', 80)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestampTz('created_at')->nullable();
                $table->timestampTz('updated_at')->nullable();

                $table->unique(['company_id', 'client_batch_id'], 'farm_livestock_batches_client_unique');
                $table->index(['company_id', 'species'], 'farm_livestock_batches_species_index');
                $table->index(['company_id', 'status'], 'farm_livestock_batches_status_index');
            });
        }

        if (! schemaTableExists('farm_animals')) {
            Schema::create('farm_animals', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->nullable()->index();

                // Un animal peut appartenir a un lot (identification par lot) ou
                // etre suivi individuellement (lot NULLABLE).
                $table->unsignedBigInteger('farm_livestock_batch_id')->nullable();
                $table->unsignedBigInteger('farm_site_id')->nullable();

                // Identifiant individuel (boucle, tatouage, puce) — DECLARE.
                $table->string('tag_number', 80);
                $table->string('name', 120)->nullable();
                $table->string('species', 24);
                $table->string('breed', 80)->nullable();
                $table->string('sex', 16)->default('unknown');
                $table->date('birth_date')->nullable();
                // Origine : `born` | `purchased` | `transferred` | `unknown`.
                $table->string('origin', 24)->default('unknown');
                $table->string('location', 160)->nullable();
                $table->string('status', 16)->default('active');
                $table->text('notes')->nullable();
                $table->string('client_animal_id', 80)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestampTz('created_at')->nullable();
                $table->timestampTz('updated_at')->nullable();

                $table->unique(['company_id', 'tag_number'], 'farm_animals_tag_unique');
                $table->unique(['company_id', 'client_animal_id'], 'farm_animals_client_unique');
                $table->index(['company_id', 'status'], 'farm_animals_status_index');
            });
        }

        if (! schemaTableExists('farm_livestock_events')) {
            Schema::create('farm_livestock_events', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->nullable()->index();

                $table->unsignedBigInteger('farm_livestock_batch_id')->nullable();
                $table->unsignedBigInteger('farm_animal_id')->nullable();
                // birth | purchase | sale | transfer_in | transfer_out | death |
                // slaughter | cull (enum FarmLivestockEventType).
                $table->string('event_type', 24);
                // Toujours POSITIVE : le sens est porte par `event_type`.
                $table->unsignedInteger('quantity')->default(1);
                // Effectif du lot APRES l'evenement : historique relisible.
                $table->unsignedInteger('head_count_after')->nullable();

                $table->timestampTz('occurred_at');
                // Contrepartie commerciale (NULLABLE) : la vente est enregistree
                // par le service de ventes EXISTANT, on ne cree pas de facture ici.
                $table->unsignedBigInteger('counterparty_id')->nullable();
                $table->decimal('unit_price', 14, 2)->nullable();
                $table->char('currency', 3)->nullable();
                $table->string('reason', 200)->nullable();
                $table->unsignedBigInteger('recorded_by')->nullable();
                $table->string('client_event_id', 80)->nullable();
                $table->timestampTz('created_at')->nullable();
                $table->timestampTz('updated_at')->nullable();

                $table->unique(['company_id', 'client_event_id'], 'farm_livestock_events_client_unique');
                $table->index(['company_id', 'farm_livestock_batch_id', 'occurred_at'], 'farm_livestock_events_batch_index');
            });
        }

        if (! schemaTableExists('farm_animal_health_events')) {
            Schema::create('farm_animal_health_events', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->nullable()->index();

                $table->unsignedBigInteger('farm_livestock_batch_id')->nullable();
                $table->unsignedBigInteger('farm_animal_id')->nullable();
                // vaccination | treatment | deworming | checkup | other
                // (enum FarmHealthEventType).
                $table->string('event_type', 24);
                $table->string('title', 160);
                // Produit / vaccin DECLARE par l'exploitant (texte libre).
                $table->string('product_name', 160)->nullable();
                // Dose DECLAREE telle quelle : Farm OS ne calcule aucun dosage.
                $table->string('dosage', 80)->nullable();
                $table->timestampTz('administered_at');
                // Rappel prevu (NULLABLE) : echeance DECLAREE, jamais deduite.
                $table->timestampTz('next_due_at')->nullable();
                // Delai d'attente STOCKE tel que declare (aucun calcul, aucune
                // prescription) — pertinent pour traitement / vermifuge.
                $table->date('withdrawal_until')->nullable();
                $table->unsignedBigInteger('veterinarian_id')->nullable();
                $table->decimal('cost_amount', 14, 2)->nullable();
                $table->char('currency', 3)->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('recorded_by')->nullable();
                $table->string('client_health_id', 80)->nullable();
                $table->timestampTz('created_at')->nullable();
                $table->timestampTz('updated_at')->nullable();

                $table->unique(['company_id', 'client_health_id'], 'farm_animal_health_client_unique');
                $table->index(['company_id', 'next_due_at'], 'farm_animal_health_due_index');
            });
        }

        if (! schemaTableExists('farm_reproduction_events')) {
            Schema::create('farm_reproduction_events', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->nullable()->index();

                $table->unsignedBigInteger('farm_livestock_batch_id')->nullable();
                $table->unsignedBigInteger('farm_animal_id')->nullable();
                // heat | mating | pregnancy_check | birth | abortion | drying_off
                // (enum FarmReproductionEventType).
                $table->string('event_type', 24);
                $table->timestampTz('occurred_at');
                // Date de mise bas PREVUE : declaree, ou calculee depuis une
                // duree de gestation SAISIE (`gestation_days`). Jamais deduite
                // d'une table d'especes que Farm OS ne possede pas.
                $table->timestampTz('expected_due_at')->nullable();
                $table->unsignedInteger('gestation_days')->nullable();
                // Nombre de nouveau-nes DECLARE (NULLABLE : inconnu a la saillie).
                $table->unsignedInteger('offspring_count')->nullable();
                $table->unsignedBigInteger('sire_animal_id')->nullable();
                $table->string('method', 40)->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('recorded_by')->nullable();
                $table->string('client_reproduction_id', 80)->nullable();
                $table->timestampTz('created_at')->nullable();
                $table->timestampTz('updated_at')->nullable();

                $table->unique(['company_id', 'client_reproduction_id'], 'farm_reproduction_client_unique');
                $table->index(['company_id', 'expected_due_at'], 'farm_reproduction_due_index');
            });
        }

        if (! schemaTableExists('farm_production_records')) {
            Schema::create('farm_production_records', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->nullable()->index();

                $table->unsignedBigInteger('farm_livestock_batch_id')->nullable();
                $table->unsignedBigInteger('farm_animal_id')->nullable();
                // milk | eggs | wool | honey | meat | manure | other
                // (enum FarmProductionType).
                $table->string('production_type', 24);
                // Quantite DECLAREE (aucune estimation de rendement).
                $table->decimal('quantity', 14, 3);
                $table->string('unit', 24);
                // Qualite / classement si disponible (NULLABLE).
                $table->string('quality_grade', 40)->nullable();
                $table->timestampTz('produced_at');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('recorded_by')->nullable();
                $table->string('client_production_id', 80)->nullable();
                $table->timestampTz('created_at')->nullable();
                $table->timestampTz('updated_at')->nullable();

                $table->unique(['company_id', 'client_production_id'], 'farm_production_client_unique');
                $table->index(['company_id', 'production_type', 'produced_at'], 'farm_production_type_index');
            });
        }

        if (! schemaTableExists('farm_feed_records')) {
            Schema::create('farm_feed_records', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->nullable()->index();

                $table->unsignedBigInteger('farm_livestock_batch_id')->nullable();
                $table->unsignedBigInteger('farm_animal_id')->nullable();
                // Ration / aliment DECLARE (texte libre) + reference optionnelle
                // vers un intrant du catalogue T2.1 (aliment pour animaux).
                $table->string('feed_name', 160);
                $table->unsignedBigInteger('farm_input_id')->nullable();
                $table->decimal('quantity', 14, 3);
                $table->string('unit', 24);
                $table->decimal('cost_amount', 14, 2)->nullable();
                $table->char('currency', 3)->nullable();
                $table->timestampTz('fed_at');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('recorded_by')->nullable();
                $table->string('client_feed_id', 80)->nullable();
                $table->timestampTz('created_at')->nullable();
                $table->timestampTz('updated_at')->nullable();

                $table->unique(['company_id', 'client_feed_id'], 'farm_feed_client_unique');
                $table->index(['company_id', 'fed_at'], 'farm_feed_date_index');
            });
        }
    }

    public function down(): void
    {
        // Ordre inverse de creation : les evenements referencent lots et animaux.
        Schema::dropIfExists('farm_feed_records');
        Schema::dropIfExists('farm_production_records');
        Schema::dropIfExists('farm_reproduction_events');
        Schema::dropIfExists('farm_animal_health_events');
        Schema::dropIfExists('farm_livestock_events');
        Schema::dropIfExists('farm_animals');
        Schema::dropIfExists('farm_livestock_batches');
    }
};
