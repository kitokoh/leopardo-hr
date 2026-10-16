<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P0-1 (audit FuelStation 2026-10-16) — catalogue de prix serveur.
 *
 * Constat : `fuel_products` ne portait AUCUN prix et `POST .../fuel-station/sales`
 * acceptait un `unit_price` fourni par le CLIENT (`StoreFuelSaleRequest`). Le
 * « prix serveur » annoncé par la documentation du module n'existait donc pas :
 * le montant était calculé serveur, mais à partir d'un prix arbitrable par
 * l'appelant — un caissier ou un terminal pouvait facturer n'importe quel prix.
 *
 * Cette migration ajoute le référentiel de prix daté (`fuel_product_prices`) :
 * un prix par (produit, unité) avec `effective_from`, ce qui fournit à la fois
 * le prix courant et l'historique des changements de prix exigés par le
 * domaine « Produits et prix ».
 *
 * Migration additive et idempotente (garde `schemaTableExists`, pattern
 * FUEL-002/003) : aucun impact sur les installations existantes. Le
 * renseignement des prix reste une opération de configuration (écriture
 * manager) ; la lecture est ouverte au tenant comme le reste du référentiel.
 *
 * `unit_price_minor` est un entier en unités mineures de la devise (cohérent
 * avec `fuel_tanks.capacity_minor` / `current_level_minor` et avec la
 * convention visée par le correctif P1 « convention monétaire »).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('fuel_product_prices')) {
            return;
        }

        Schema::create('fuel_product_prices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();

            // Produit du catalogue tenant (`fuel_products.code`, unique par tenant).
            $table->string('product_code', 80);

            // Unité de mesure facturée (`l`, `kg`, `unit`, …) — même vocabulaire
            // que `fuel_products.unit_code`.
            $table->string('unit_code', 20)->default('l');

            // Prix de vente en unités mineures (centimes) — jamais un float.
            $table->unsignedBigInteger('unit_price_minor');

            // Validité : le prix courant est le `effective_from` le plus récent
            // antérieur ou égal à l'instant de la vente.
            $table->timestampTz('effective_from')->useCurrent();
            $table->timestampTz('effective_to')->nullable();

            $table->string('status', 20)->default('active'); // active|inactive
            $table->unsignedInteger('created_by')->nullable()->index();
            $table->text('notes')->nullable();

            $table->timestamps();

            // Un seul prix par (produit, unité, date d'effet).
            $table->unique(
                ['company_id', 'product_code', 'unit_code', 'effective_from'],
                'fuel_product_prices_effective_unique'
            );

            // Index de résolution : prix courant d'un produit à un instant donné.
            $table->index(
                ['company_id', 'product_code', 'unit_code', 'effective_from'],
                'fuel_product_prices_resolution_idx'
            );

            // FK composite anti cross-tenant vers le catalogue produits
            // (pattern FUEL-003) : un prix ne peut référencer que le produit du
            // même tenant.
            $table->foreign(['product_code', 'company_id'], 'fuel_product_prices_product_company_fk')
                ->references(['code', 'company_id'])
                ->on('fuel_products')
                ->cascadeOnDelete();
        });

        $this->addChecks();
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_product_prices');
    }

    private function constraintExists(string $name): bool
    {
        return DB::selectOne('SELECT 1 FROM pg_constraint WHERE conname = ?', [$name]) !== null;
    }

    private function addChecks(): void
    {
        $schema = resolveTableSchema('fuel_product_prices');

        if ($schema === null) {
            return;
        }

        // Contrainte de cohérence métier : le prix est strictement positif et
        // la fenêtre de validité est bien ordonnée. Une correction de prix ne
        // peut pas écrire un prix nul (offre, erreur de saisie, …).
        if (! $this->constraintExists('fuel_product_prices_amount_check')) {
            DB::statement(
                "ALTER TABLE {$schema}.fuel_product_prices ADD CONSTRAINT fuel_product_prices_amount_check CHECK (unit_price_minor > 0)"
            );
        }

        if (! $this->constraintExists('fuel_product_prices_window_check')) {
            DB::statement(
                "ALTER TABLE {$schema}.fuel_product_prices ADD CONSTRAINT fuel_product_prices_window_check CHECK (effective_to IS NULL OR effective_to > effective_from)"
            );
        }

        if (! $this->constraintExists('fuel_product_prices_status_check')) {
            DB::statement(
                "ALTER TABLE {$schema}.fuel_product_prices ADD CONSTRAINT fuel_product_prices_status_check CHECK (status IN ('active', 'inactive'))"
            );
        }
    }
};
