<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Farm OS — T4.1 Achats, ventes, depenses et rentabilite (BC-37 AGRICULTURE, #8704).
 *
 * REGLE ABSOLUE DE CETTE MIGRATION : Farm OS ne cree AUCUN systeme financier
 * parallele. Il n'y a ici ni facture, ni paiement, ni ecriture comptable, ni
 * grand livre. Les documents commerciaux et les reglements restent dans les
 * services EXISTANTS de Leopardo (ventes, facturation, comptabilite) ; Farm OS
 * ne fait que les REFERENCER par identifiant.
 *
 * Ce que Farm OS possede reellement :
 *   - `farm_expenses` : une depense AFFECTEE a un axe d'analyse agricole
 *     (exploitation, parcelle, culture, lot d'animaux, campagne). C'est la
 *     seule table financiere de Farm OS, et elle est DECLARATIVE ;
 *   - `farm_sales_links` : le lien entre un LOT DE RECOLTE (ou un lot d'animaux)
 *     et un document de vente EXISTANT. Aucune donnee commerciale n'est
 *     dupliquee : on stocke l'identifiant du document, la quantite imputee et
 *     le montant impute, pour eviter le double comptage du chiffre d'affaires ;
 *   - `farm_campaigns` : la campagne agricole (annee culturale), axe de
 *     regroupement des couts et des produits.
 *
 * HONNETETE DES CHIFFRES (exigence de la mission) : la rentabilite ne doit
 * JAMAIS etre presentee comme certaine quand les donnees sont incompletes. Le
 * schema le permet en distinguant :
 *   - `amount` NOT NULL sur une depense = montant REEL declare ;
 *   - `is_estimated` = true = montant ESTIME par l'exploitant (pas une facture) ;
 *   - l'ABSENCE de ligne = donnee MANQUANTE (jamais remplacee par zero).
 * Le service de rentabilite expose ces trois etats separement et refuse de
 * presenter une marge comme fiable si des donnees manquent.
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
        if (! schemaTableExists('farm_campaigns')) {
            Schema::create('farm_campaigns', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->nullable()->index();

                $table->string('name', 160);
                // Annee culturale de debut (ex. 2026 pour 2026/2027).
                $table->unsignedSmallInteger('start_year');
                $table->date('starts_on')->nullable();
                $table->date('ends_on')->nullable();
                // open | closed (une campagne close n'accepte plus de depense).
                $table->string('status', 16)->default('open');
                $table->text('notes')->nullable();
                $table->string('client_campaign_id', 80)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestampTz('created_at')->nullable();
                $table->timestampTz('updated_at')->nullable();

                $table->unique(['company_id', 'name'], 'farm_campaigns_name_unique');
                $table->unique(['company_id', 'client_campaign_id'], 'farm_campaigns_client_unique');
                $table->index(['company_id', 'start_year'], 'farm_campaigns_year_index');
            });
        }

        if (! schemaTableExists('farm_expenses')) {
            Schema::create('farm_expenses', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->nullable()->index();

                // Axes d'analyse agricoles : tous NULLABLES, une depense peut
                // n'etre rattachee qu'a l'exploitation.
                $table->unsignedBigInteger('farm_site_id')->nullable();
                $table->unsignedBigInteger('parcel_id')->nullable();
                $table->unsignedBigInteger('crop_cycle_id')->nullable();
                $table->unsignedBigInteger('farm_livestock_batch_id')->nullable();
                $table->unsignedBigInteger('farm_equipment_id')->nullable();
                $table->unsignedBigInteger('farm_campaign_id')->nullable();

                // Categorie du registre FERME cote application (seeds, fertilizer,
                // phytosanitary, feed, veterinary, labour, equipment_maintenance,
                // fuel, storage, transport, irrigation, other).
                $table->string('category', 40);
                $table->string('label', 200);
                // Montant REEL declare (jamais un zero de remplissage).
                $table->decimal('amount', 14, 2);
                $table->char('currency', 3);
                // true = montant ESTIME par l'exploitant, pas une piece comptable.
                $table->boolean('is_estimated')->default(false);
                $table->date('spent_on');
                // Fournisseur : reference vers le service d'achats EXISTANT.
                $table->unsignedBigInteger('supplier_id')->nullable();
                $table->unsignedBigInteger('purchase_document_id')->nullable();
                // Intrant consomme (T2.1) : permet le cout des intrants par axe.
                $table->unsignedBigInteger('farm_input_id')->nullable();
                $table->decimal('quantity', 14, 3)->nullable();
                $table->string('unit', 24)->nullable();
                // Justificatif : reference vers le service de FICHIERS existant.
                $table->unsignedBigInteger('file_id')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('recorded_by')->nullable();
                $table->string('client_expense_id', 80)->nullable();
                $table->timestampTz('created_at')->nullable();
                $table->timestampTz('updated_at')->nullable();

                $table->unique(['company_id', 'client_expense_id'], 'farm_expenses_client_unique');
                $table->index(['company_id', 'spent_on'], 'farm_expenses_date_index');
                $table->index(['company_id', 'category'], 'farm_expenses_category_index');
                $table->index(['company_id', 'parcel_id'], 'farm_expenses_parcel_index');
                $table->index(['company_id', 'farm_livestock_batch_id'], 'farm_expenses_batch_index');
                $table->index(['company_id', 'farm_campaign_id'], 'farm_expenses_campaign_index');
            });
        }

        if (! schemaTableExists('farm_sales_links')) {
            Schema::create('farm_sales_links', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->nullable()->index();

                // Document de vente EXISTANT (service ventes / facturation).
                // Farm OS ne cree ni facture ni paiement : il pointe.
                $table->unsignedBigInteger('sale_document_id');
                $table->unsignedBigInteger('sale_line_id')->nullable();

                // Objet vendu : lot de recolte (T3.2) ou lot d'animaux (T3.1).
                $table->unsignedBigInteger('farm_harvest_lot_id')->nullable();
                $table->unsignedBigInteger('farm_livestock_batch_id')->nullable();

                // Quantite et montant IMPUTES a cet objet, pour eviter qu'un
                // document multi-lignes ne soit compte deux fois.
                $table->decimal('quantity', 14, 3)->nullable();
                $table->string('unit', 24)->nullable();
                $table->decimal('amount', 14, 2);
                $table->char('currency', 3);
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->date('sold_on');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('recorded_by')->nullable();
                $table->string('client_sale_link_id', 80)->nullable();
                $table->timestampTz('created_at')->nullable();
                $table->timestampTz('updated_at')->nullable();

                $table->unique(['company_id', 'client_sale_link_id'], 'farm_sales_links_client_unique');
                // Un meme document ne peut etre impute qu'UNE fois au meme lot :
                // c'est la garde anti-double-comptage du chiffre d'affaires.
                $table->unique(['company_id', 'sale_document_id', 'farm_harvest_lot_id'], 'farm_sales_links_doc_lot_unique');
                $table->index(['company_id', 'sold_on'], 'farm_sales_links_date_index');
                $table->index(['company_id', 'farm_harvest_lot_id'], 'farm_sales_links_lot_index');
                $table->index(['company_id', 'farm_livestock_batch_id'], 'farm_sales_links_batch_index');
            });
        }
    }

    public function down(): void
    {
        // Ordre inverse de creation : les liens et depenses referencent la campagne.
        Schema::dropIfExists('farm_sales_links');
        Schema::dropIfExists('farm_expenses');
        Schema::dropIfExists('farm_campaigns');
    }
};
