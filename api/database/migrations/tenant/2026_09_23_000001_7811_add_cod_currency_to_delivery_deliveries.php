<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #7811 — handoff livraison BC-26 des commandes en ligne retail :
 * la livraison COD porte le montant à encaisser (`cod_amount_minor`,
 * colonne existante) ET sa devise. `delivery_deliveries` n'avait aucune
 * colonne devise : ajout de `cod_currency` (ISO 4217, nullable — les
 * livraisons sans COD ou historiques restent NULL).
 *
 * Règle « une table, une migration » : nouvelle migration ALTER dédiée,
 * idempotente (garde Schema::hasColumn), SANS foreign key.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('delivery_deliveries')) {
            return;
        }

        if (! Schema::hasColumn('delivery_deliveries', 'cod_currency')) {
            Schema::table('delivery_deliveries', function (Blueprint $table): void {
                $table->string('cod_currency', 3)->nullable()->after('cod_amount_minor');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('delivery_deliveries')
            && Schema::hasColumn('delivery_deliveries', 'cod_currency')) {
            Schema::table('delivery_deliveries', function (Blueprint $table): void {
                $table->dropColumn('cod_currency');
            });
        }
    }
};
