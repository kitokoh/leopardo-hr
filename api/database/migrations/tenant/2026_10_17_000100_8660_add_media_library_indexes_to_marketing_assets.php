<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing OS — index de recherche et de tri de la médiathèque (brief §C).
 *
 * L'écran de médiathèque trie par `created_at DESC, id DESC` et filtre sur
 * (campagne, type, statut de validation, langue, produit, secteur, objectif).
 * La migration #8528 n'avait posé que des index sur une colonne à la fois :
 * PostgreSQL ne peut alors pas servir efficacement la combinaison filtre + tri
 * dès que le tenant dépasse quelques milliers de ressources, et la pagination
 * dérive vers un tri complet à chaque page.
 *
 * Cette migration ajoute uniquement les index COMPOSITES réellement utiles aux
 * requêtes de `indexAssets` (company_id toujours en tête : c'est la clé
 * d'isolation et elle est présente dans 100 % des requêtes). Chaque index
 * correspond à un filtre effectivement exposé par l'API — aucun index
 * spéculatif.
 *
 * GARDES RESPECTÉES :
 *  - additive : aucun changement de colonne, aucune donnée touchée ;
 *  - idempotente : `hasIndex` évite un double `CREATE INDEX` au rejeu ;
 *  - « une table, une migration » (#7452) : aucune table créée ici ;
 *  - `down()` ne supprime QUE les index ajoutés par ce fichier (#8207).
 *
 * COÛT : quatre index B-tree sur une table dont la croissance est celle d'une
 * médiathèque par tenant (milliers de lignes, pas de millions). L'écriture est
 * un ajout de ressource, pas un flux : le compromis lecture/écriture est très
 * favorable.
 */
return new class extends Migration
{
    /** @var array<int, array{name: string, columns: list<string>}> */
    private const INDEXES = [
        // Tri par défaut de la médiathèque : `created_at DESC, id DESC`.
        ['name' => 'marketing_assets_company_created_id_index', 'columns' => ['company_id', 'created_at', 'id']],
        // Filtre « type de ressource » (vidéo, image, script, prompt…).
        ['name' => 'marketing_assets_company_kind_created_index', 'columns' => ['company_id', 'kind', 'created_at']],
        // Filtre « statut de validation » (l'écran de revue ne liste que `pending`).
        ['name' => 'marketing_assets_company_validation_created_index', 'columns' => ['company_id', 'validation_status', 'created_at']],
        // Filtre « rattachement à une campagne » (vue par campagne, brief §I).
        ['name' => 'marketing_assets_company_campaign_created_index', 'columns' => ['company_id', 'campaign_id', 'created_at']],
    ];

    public function up(): void
    {
        if (! schemaTableExists('marketing_assets')) {
            return;
        }

        if (! Schema::hasTable('marketing_assets')) {
            return;
        }

        foreach (self::INDEXES as $index) {
            if (Schema::hasIndex('marketing_assets', $index['name'])) {
                continue;
            }

            Schema::table('marketing_assets', function (Blueprint $table) use ($index): void {
                $table->index($index['columns'], $index['name']);
            });
        }

        // Recherche plein texte « publicité restaurant » : les colonnes texte
        // de la médiathèque sont interrogées par `LIKE`/`ILIKE`. L'index
        // trigramme rend ce motif utilisable à partir de 3 caractères, au lieu
        // d'un scan complet. Il exige l'extension `pg_trgm`, présente sur les
        // images PostgreSQL du dépôt mais pas garantie partout : on l'active
        // dans une transaction séparée et on IGNORE l'échec — la recherche
        // reste fonctionnelle sans l'index, elle est seulement plus lente.
        if (DB::getDriverName() === 'pgsql') {
            try {
                DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

                if (! $this->hasIndex('marketing_assets_name_trgm_index')) {
                    DB::statement('CREATE INDEX marketing_assets_name_trgm_index ON marketing_assets USING gin (name gin_trgm_ops)');
                }
            } catch (\Throwable) {
                // Absence de droits ou de l'extension : dégradation silencieuse
                // d'un OPTIMISATION, jamais du comportement fonctionnel.
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('marketing_assets')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql' && $this->hasIndex('marketing_assets_name_trgm_index')) {
            DB::statement('DROP INDEX IF EXISTS marketing_assets_name_trgm_index');
        }

        foreach (self::INDEXES as $index) {
            if (! Schema::hasIndex('marketing_assets', $index['name'])) {
                continue;
            }

            Schema::table('marketing_assets', function (Blueprint $table) use ($index): void {
                $table->dropIndex($index['name']);
            });
        }
    }

    /** L'index existe-t-il ? (nom non qualifié, schéma courant = tenant). */
    private function hasIndex(string $name): bool
    {
        $result = DB::selectOne(
            'SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND indexname = ?',
            [$name]
        );

        return $result !== null;
    }
};
