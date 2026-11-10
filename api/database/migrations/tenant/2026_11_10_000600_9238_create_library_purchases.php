<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BC-40 LIBRARY / BC-38 PAIEMENTS (#9238) — achat à l'unité et vente en gros.
 *
 * Deux apports, dans le même fichier parce qu'ils forment **une seule**
 * décision produit (voir le commentaire de décisions de #9238) :
 *
 *  1. `library_resources.vat_rate` + `library_resources.wholesale_tiers` —
 *     le taux de TVA et les paliers de quantité sont portés par la RESSOURCE.
 *     Un livre numérique et un livre papier n'ont pas le même taux dans les
 *     juridictions du dépôt, et un prix de gros dépend du titre vendu : ces
 *     deux attributs n'ont donc aucun sens au niveau global.
 *  2. `library_purchases` — la vente elle-même : montants figés au moment de
 *     l'achat (le prix du catalogue peut changer demain, la facture non),
 *     taux de TVA figé, numéro de facture séquentiel par tenant.
 *
 * ⚠️ ISOLATION TENANT STRICTE : `company_id` est NON NULLABLE. Une vente
 * appartient toujours à un tenant et à un employé précis ; il n'existe aucune
 * vente « publique » ou anonyme.
 *
 * ⚠️ IDEMPOTENCE : le webhook de paiement peut être rejoué autant de fois que
 * le fournisseur l'exige. `UNIQUE (company_id, idempotency_key)` garantit
 * qu'un rejeu ne crée jamais une seconde vente, et
 * `UNIQUE (company_id, invoice_number)` qu'un numéro de facture n'est jamais
 * réutilisé — c'est la BASE qui le garantit, pas un `if`.
 *
 * Idempotente + `down()` strictement limité à ce que ce `up()` a défini
 * (leçon migration #8207).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('library_purchases')) {
            Schema::create('library_purchases', function (Blueprint $table): void {
                $table->id();

                $table->uuid('company_id')->index();

                // L'acheteur est TOUJOURS un employé du tenant : jamais un
                // visiteur anonyme (le catalogue public reste lisible sans
                // compte pour les ressources gratuites).
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('resource_id');

                $table->unsignedInteger('quantity')->default(1);

                // Montants en minor units, figés à la vente. `total_minor` est
                // TTC par convention du dépôt ; `net + vat === total` à
                // l'unité près (arrondi porté par `vat_minor`).
                $table->unsignedBigInteger('unit_price_minor');
                $table->unsignedBigInteger('total_minor');
                $table->unsignedBigInteger('net_minor');
                $table->unsignedBigInteger('vat_minor')->default(0);
                $table->decimal('vat_rate', 5, 4)->default(0);
                $table->char('currency', 3);

                $table->string('status', 20)->default('pending');

                // Clé d'idempotence fournie par l'appelant : un double clic ou
                // un rejeu de webhook retombe sur la MÊME vente.
                $table->string('idempotency_key', 191);

                // Référence de la transaction de paiement (BC-38). Nulle tant
                // que le paiement n'est pas initié : l'accès est refusé dans
                // cet état (fail-closed).
                $table->unsignedBigInteger('payment_transaction_id')->nullable()->index();
                $table->string('payment_reference', 191)->nullable();

                // Facture : allouée UNIQUEMENT à la confirmation du paiement,
                // jamais à la création (une commande abandonnée ne doit pas
                // consommer de numéro fiscal).
                $table->string('invoice_number', 40)->nullable();

                $table->timestamp('paid_at')->nullable();
                $table->timestamp('refunded_at')->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'idempotency_key'], 'library_purchases_company_idempotency_unique');
                $table->unique(['company_id', 'invoice_number'], 'library_purchases_company_invoice_unique');
                $table->index(['company_id', 'employee_id', 'resource_id'], 'library_purchases_buyer_idx');
                $table->index(['company_id', 'resource_id', 'status'], 'library_purchases_access_idx');
            });

            DB::statement("COMMENT ON TABLE library_purchases IS 'Ventes de la bibliotheque - isolation tenant stricte (company_id NOT NULL), idempotence par cle et numerotation de facture par tenant.';");
        }

        // Colonnes de tarification portées par la ressource. Ajout IDEMPOTENT
        // (`schemaHasColumn`) : la table existe déjà depuis #9200, on ne la
        // recrée jamais (garde « une table, une migration », #7452).
        if (schemaTableExists('library_resources') && ! schemaHasColumn('library_resources', 'vat_rate')) {
            Schema::table('library_resources', function (Blueprint $table): void {
                $table->decimal('vat_rate', 5, 4)->nullable();
            });
        }

        if (schemaTableExists('library_resources') && ! schemaHasColumn('library_resources', 'wholesale_tiers')) {
            Schema::table('library_resources', function (Blueprint $table): void {
                $table->json('wholesale_tiers')->nullable();
            });
        }
    }

    public function down(): void
    {
        // On ne détruit QUE ce que ce fichier a créé/ajouté.
        if (schemaTableExists('library_purchases')) {
            Schema::drop('library_purchases');
        }

        if (schemaTableExists('library_resources') && schemaHasColumn('library_resources', 'wholesale_tiers')) {
            Schema::table('library_resources', function (Blueprint $table): void {
                $table->dropColumn('wholesale_tiers');
            });
        }

        if (schemaTableExists('library_resources') && schemaHasColumn('library_resources', 'vat_rate')) {
            Schema::table('library_resources', function (Blueprint $table): void {
                $table->dropColumn('vat_rate');
            });
        }
    }
};
