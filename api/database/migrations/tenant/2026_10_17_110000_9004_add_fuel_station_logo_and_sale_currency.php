<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 6 (audit 2026-10-17) - P1-c (convention monetaire) et P1-e (logo station).
 *
 * P1-c : `fuel_sales.currency` rend la devise EXPLICITE sur chaque vente, au
 * lieu de la laisser implicite (devise du tenant supposee). La convention
 * documentee du module (entiers en unites mineures) est exposee de facon
 * ADDITIVE par les champs derives `amount_minor` / `unit_price_minor` /
 * `quantity_minor` du payload : les colonnes `decimal(14,2)` restent en place,
 * donc aucun client existant (app mobile gelee) n'est casse. La bascule
 * destructive des colonnes reste planifiee APRES le degel mobile (issue #8581).
 *
 * P1-e : `fuel_stations.logo_path|logo_disk|logo_url` - identite visuelle de
 * station. Meme mecanisme de stockage que le branding societe
 * (CompanyBrandingController : disque `public`, chemin par tenant) : aucun
 * stockage parallele n'est introduit.
 *
 * Migration idempotente (rejouable sans erreur).
 */
return new class extends Migration
{
    public function up(): void
    {
        $salesSchema = resolveTableSchema('fuel_sales');

        if ($salesSchema !== null && ! $this->columnExists($salesSchema, 'fuel_sales', 'currency')) {
            Schema::table('fuel_sales', function (Blueprint $table): void {
                $table->string('currency', 10)->nullable()->after('amount');
            });
        }

        $stationsSchema = resolveTableSchema('fuel_stations');

        if ($stationsSchema !== null) {
            $hasLogoPath = $this->columnExists($stationsSchema, 'fuel_stations', 'logo_path');
            $hasLogoDisk = $this->columnExists($stationsSchema, 'fuel_stations', 'logo_disk');
            $hasLogoUrl = $this->columnExists($stationsSchema, 'fuel_stations', 'logo_url');

            if (! $hasLogoPath || ! $hasLogoDisk || ! $hasLogoUrl) {
                Schema::table('fuel_stations', function (Blueprint $table) use ($hasLogoPath, $hasLogoDisk, $hasLogoUrl): void {
                    if (! $hasLogoPath) {
                        $table->string('logo_path', 255)->nullable();
                    }

                    if (! $hasLogoDisk) {
                        $table->string('logo_disk', 20)->nullable();
                    }

                    if (! $hasLogoUrl) {
                        $table->string('logo_url', 2048)->nullable();
                    }
                });
            }
        }
    }

    public function down(): void
    {
        $salesSchema = resolveTableSchema('fuel_sales');

        if ($salesSchema !== null && $this->columnExists($salesSchema, 'fuel_sales', 'currency')) {
            Schema::table('fuel_sales', function (Blueprint $table): void {
                $table->dropColumn('currency');
            });
        }

        $stationsSchema = resolveTableSchema('fuel_stations');

        if ($stationsSchema !== null) {
            foreach (['logo_path', 'logo_disk', 'logo_url'] as $column) {
                if ($this->columnExists($stationsSchema, 'fuel_stations', $column)) {
                    Schema::table('fuel_stations', function (Blueprint $table) use ($column): void {
                        $table->dropColumn($column);
                    });
                }
            }
        }
    }

    private function columnExists(?string $schema, string $table, string $column): bool
    {
        if ($schema === null) {
            return false;
        }

        $row = DB::selectOne(
            'SELECT 1 FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?',
            [$schema, $table, $column]
        );

        return $row !== null;
    }
};
