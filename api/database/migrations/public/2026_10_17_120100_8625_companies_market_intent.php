<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BOS-O-01 / BOS-O-02 / #8625 — porter l'intention de marché jusqu'au tenant.
 *
 * Constat d'audit (gap matrix O-01/O-02) : la vitrine sait qu'un visiteur
 * arrive par le point d'entrée « Trade » et choisit « international »
 * (importateur/exportateur) ou « domestique » (grossiste/détail), mais cette
 * intention s'arrêtait au `sessionStorage` du navigateur. Le tenant naissait
 * sans marché ni profil, et le superadmin n'avait aucun moyen de segmenter
 * les espaces réellement créés.
 *
 * Deux dimensions distinctes, volontairement séparées :
 *   - `market_type`  : le MARCHÉ (international | domestic) ;
 *   - `trade_profile`: le PROFIL dans ce marché
 *                      (importer | exporter | both | wholesaler | retailer).
 *
 * Les deux colonnes sont NULLABLES et sans remplissage rétroactif : un tenant
 * créé hors du parcours Trade n'a pas d'intention déclarée, et cette absence
 * doit rester lisible (un `domestic` par défaut mentirait sur l'intention).
 * La validation est fail-closed côté API (allowlist serveur) ; la base pose
 * seulement un CHECK pour interdire l'écriture directe d'une valeur inconnue.
 *
 * Migration additive, idempotente et rejouable.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    /** @var list<string> */
    private const MARKET_TYPES = ['international', 'domestic'];

    /** @var list<string> */
    private const TRADE_PROFILES = ['importer', 'exporter', 'both', 'wholesaler', 'retailer'];

    public function up(): void
    {
        DB::statement('SET search_path TO public');

        if (! Schema::hasTable('companies')) {
            return;
        }

        if (! Schema::hasColumn('companies', 'market_type')) {
            Schema::table('companies', function (Blueprint $table): void {
                $table->string('market_type', 20)->nullable();
            });
        }

        if (! Schema::hasColumn('companies', 'trade_profile')) {
            Schema::table('companies', function (Blueprint $table): void {
                $table->string('trade_profile', 30)->nullable();
            });
        }

        $this->ensureCheckConstraint(
            'companies_market_type_check',
            'market_type IS NULL OR market_type IN (\''.implode("','", self::MARKET_TYPES).'\')'
        );

        $this->ensureCheckConstraint(
            'companies_trade_profile_check',
            'trade_profile IS NULL OR trade_profile IN (\''.implode("','", self::TRADE_PROFILES).'\')'
        );

        // Segmentation commerciale (superadmin) : les deux colonnes sont
        // interrogées ensemble, toujours avec un filtre NULL possible.
        if (! $this->indexExists('companies_market_type_idx')) {
            DB::statement('CREATE INDEX IF NOT EXISTS companies_market_type_idx ON public.companies (market_type)');
        }
    }

    public function down(): void
    {
        DB::statement('SET search_path TO public');

        if (! Schema::hasTable('companies')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS public.companies_market_type_idx');
        DB::statement('ALTER TABLE public.companies DROP CONSTRAINT IF EXISTS companies_market_type_check');
        DB::statement('ALTER TABLE public.companies DROP CONSTRAINT IF EXISTS companies_trade_profile_check');

        foreach (['trade_profile', 'market_type'] as $column) {
            if (Schema::hasColumn('companies', $column)) {
                Schema::table('companies', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }

    private function ensureCheckConstraint(string $name, string $expression): void
    {
        $exists = DB::selectOne(
            'SELECT 1 FROM pg_constraint WHERE conname = ? AND conrelid = \'public.companies\'::regclass',
            [$name]
        );

        if ($exists !== null) {
            return;
        }

        DB::statement("ALTER TABLE public.companies ADD CONSTRAINT {$name} CHECK ({$expression})");
    }

    private function indexExists(string $index): bool
    {
        return DB::selectOne(
            'SELECT 1 FROM pg_indexes WHERE schemaname = ? AND indexname = ?',
            ['public', $index]
        ) !== null;
    }
};
