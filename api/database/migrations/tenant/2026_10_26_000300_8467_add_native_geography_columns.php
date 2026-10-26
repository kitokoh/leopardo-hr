<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8467 (BC-34 GEO, déviation D2) — colonnes `geography(Point,4326)` natives.
 *
 * ## Le problème que cette migration corrige
 *
 * Le core géo reconstruisait chaque point à la volée depuis une paire
 * `decimal(10,7)` : `ST_SetSRID(ST_MakePoint(longitude, latitude), 4326)`.
 * Conséquence concrète : **aucun index spatial ne pouvait être utilisé**. Un
 * `ST_DWithin` sur une expression calculée oblige PostgreSQL à évaluer la
 * fonction sur CHAQUE ligne de la table avant de filtrer — un scan complet,
 * même avec un index GIST sur les colonnes décimales (qui n'existe pas non
 * plus). À l'échelle d'une flotte VTC, la recherche « chauffeur le plus
 * proche » devient linéaire en nombre de positions historiques.
 *
 * ## Ce que fait cette migration
 *
 * Pour chaque table porteuse de coordonnées, on ajoute une colonne
 * `geography(Point, 4326)` **générée** (STORED) depuis les colonnes
 * décimales existantes, puis un index GIST dessus.
 *
 * **Générée plutôt que maintenue par du code applicatif** — c'est la décision
 * structurante de cette issue. Une colonne écrite par l'application exigerait
 * une double écriture dans chaque service, chaque import, chaque fixture : un
 * seul chemin oublié et la colonne diverge silencieusement des décimales, avec
 * des résultats de recherche faux sans aucune erreur. Une colonne `GENERATED
 * ALWAYS AS ... STORED` est recalculée par PostgreSQL à chaque écriture : elle
 * ne PEUT pas diverger. Le prix est la perte de l'écriture directe (on ne peut
 * plus `SET location = ...`), ce qui est précisément la garantie voulue.
 *
 * **Pourquoi on garde les colonnes décimales** — elles restent la source de
 * vérité en écriture (l'API, les formulaires, les exports CSV parlent en
 * lat/lng) et le repli Haversine s'en sert quand PostGIS est absent. Les
 * supprimer aurait cassé les clients et le mode dégradé pour un gain nul : la
 * colonne générée coûte 32 octets par ligne et un index.
 *
 * ## Portée volontairement limitée
 *
 * Cette migration traite les **trois tables VTC** (`vtc_drivers`,
 * `vtc_rides`, `vtc_driver_positions`) : c'est le vertical où la recherche
 * proximale est dans le chemin critique (dispatch, tarification, suivi temps
 * réel). Fleet / Delivery / Travel / Hospitality suivent le même patron et
 * sont traités dans une migration dédiée par vertical, comme le demande la
 * spec §D2 — une migration unique couvrant 12 tables serait irréversible en
 * pratique.
 *
 * **Idempotente** : `IF NOT EXISTS` partout. Une base sans PostGIS (test,
 * environnement non provisionné) voit la migration **ne rien faire** plutôt
 * qu'échouer : le mode Haversine reste pleinement fonctionnel.
 */
return new class extends Migration
{
    /**
     * Tables VTC et leurs paires de colonnes de coordonnées.
     *
     * Une table peut porter PLUSIEURS points (un trajet a un départ et une
     * arrivée) : chaque point devient sa propre colonne générée.
     *
     * @var array<string, array<string, array{0: string, 1: string}>>
     */
    private const TARGETS = [
        'vtc_drivers' => [
            'current_location' => ['current_latitude', 'current_longitude'],
        ],
        'vtc_rides' => [
            'pickup_location' => ['pickup_latitude', 'pickup_longitude'],
            'dropoff_location' => ['dropoff_latitude', 'dropoff_longitude'],
        ],
        'vtc_driver_positions' => [
            'location' => ['latitude', 'longitude'],
        ],
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if (! $this->postgisAvailable()) {
            // Mode dégradé assumé : le core géo retombe sur Haversine, et une
            // colonne `geography` n'aurait aucune sémantique ici.
            return;
        }

        foreach (self::TARGETS as $table => $points) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($points as $column => [$latColumn, $lngColumn]) {
                if (! Schema::hasColumn($table, $latColumn) || ! Schema::hasColumn($table, $lngColumn)) {
                    continue;
                }

                // 1) Colonne générée : jamais désynchronisée, par construction.
                //    STORED (pas VIRTUAL) pour être indexable en GIST.
                //
                //    ⚠️ QUALIFICATION `public.` OBLIGATOIRE sur le type ET les
                //    fonctions. Cette migration tourne dans le schéma tenant
                //    (`shared_tenants`), or PostGIS s'installe dans `public` :
                //    le `search_path` de la session est `shared_tenants` seul,
                //    donc `geography`, `ST_SetSRID`, `ST_MakePoint` sont
                //    INTROUVABLES (SQLSTATE 42704 / 42883, vérifié en base).
                DB::statement(sprintf(
                    'ALTER TABLE %1$s ADD COLUMN IF NOT EXISTS %2$s public.geography(Point, 4326) '
                    .'GENERATED ALWAYS AS (CASE WHEN %3$s IS NULL OR %4$s IS NULL THEN NULL '
                    .'ELSE public.ST_SetSRID(public.ST_MakePoint(%4$s::double precision, %3$s::double precision), 4326)::public.geography END) STORED',
                    $this->quote($table),
                    $this->quote($column),
                    $this->quote($latColumn),
                    $this->quote($lngColumn),
                ));

                // 2) Index SPATIAL — c'est lui qui rend `ST_DWithin` logarithmique
                //    au lieu d'un scan complet. Sans lui la colonne ne sert à rien.
                DB::statement(sprintf(
                    'CREATE INDEX IF NOT EXISTS %1$s ON %2$s USING GIST (%3$s)',
                    $this->quote($table.'_'.$column.'_gist'),
                    $this->quote($table),
                    $this->quote($column),
                ));
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::TARGETS as $table => $points) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (array_keys($points) as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                DB::statement(sprintf(
                    'DROP INDEX IF EXISTS %s',
                    $this->quote($table.'_'.$column.'_gist'),
                ));

                DB::statement(sprintf(
                    'ALTER TABLE %s DROP COLUMN IF EXISTS %s',
                    $this->quote($table),
                    $this->quote($column),
                ));
            }
        }
    }

    private function postgisAvailable(): bool
    {
        try {
            return DB::selectOne("SELECT 1 AS ok FROM pg_extension WHERE extname = 'postgis'") !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Identifiants quotés. Tous viennent de constantes du fichier, jamais d'une
     * entrée utilisateur — mais on quote quand même : une migration qui
     * interpole un identifiant sans le quoter est le point de départ d'une
     * injection dès qu'un jour la liste devient dynamique.
     */
    private function quote(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
};
