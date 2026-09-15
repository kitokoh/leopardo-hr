<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * #7417 — Aligne le schéma des annonces sur le contrat applicatif.
 *
 * Deux migrations créent les mêmes tables avec des colonnes DIFFÉRENTES :
 *
 *   2026_08_30_000017_6110_create_travel_advert_tables.php
 *       travel_advert_types    → code, NAME, is_active
 *       travel_advert_positions→ code, NAME, is_active
 *       travel_advert_prices   → TYPE_ID, POSITION_ID, price_per_*
 *
 *   2026_08_30_000921_6108_create_travel_advert_reference_tables.php
 *       travel_advert_types    → code, LABEL
 *       travel_advert_positions→ code, LABEL
 *
 * Toutes sont gardées par `schemaTableExists(...)`. `000017` ayant un
 * horodatage antérieur, c'est ELLE qui crée les tables et `000921` devient un
 * no-op silencieux : le schéma réel porte `name` / `type_id` / `position_id`.
 *
 * Or tout le code consomme `label` / `advert_type_id` / `advert_position_id`
 * (`TravelAdvertType`, `TravelAdvertPosition`, `TravelAdvertPrice`, leurs
 * relations, et les tests). Résultat en production sur une base fraîchement
 * migrée :
 *
 *   POST /api/v1/travel/advert-types {"code":"banner","label":"Bannière"}
 *   → 500  PDOException: column "label" of relation "travel_advert_types"
 *          does not exist
 *
 * Tout le référentiel d'annonces (types, positions, grille tarifaire) était
 * donc hors service.
 *
 * Correctif ADDITIF (idempotent, sûr sur un environnement déjà déployé) :
 * on renomme les colonnes divergentes vers le nom attendu par le code, et
 * uniquement lorsque l'ancienne existe et la nouvelle pas. Les index/contraintes
 * suivent automatiquement le renommage côté PostgreSQL.
 */
return new class extends Migration
{
    /**
     * Colonnes à aligner : [table => [ancien nom, nouveau nom]].
     *
     * @var array<string, array<int, array{0: string, 1: string}>>
     */
    private const RENAMES = [
        'travel_advert_types' => [
            ['name', 'label'],
        ],
        'travel_advert_positions' => [
            ['name', 'label'],
        ],
        'travel_advert_prices' => [
            ['type_id', 'advert_type_id'],
            ['position_id', 'advert_position_id'],
        ],
    ];

    public function up(): void
    {
        $this->renameColumns(self::RENAMES);
    }

    public function down(): void
    {
        $reversed = [];

        foreach (self::RENAMES as $table => $pairs) {
            foreach ($pairs as [$from, $to]) {
                $reversed[$table][] = [$to, $from];
            }
        }

        $this->renameColumns($reversed);
    }

    /**
     * @param  array<string, array<int, array{0: string, 1: string}>>  $map
     */
    private function renameColumns(array $map): void
    {
        foreach ($map as $table => $pairs) {
            if (! schemaTableExists($table)) {
                continue;
            }

            foreach ($pairs as [$from, $to]) {
                // Idempotent : on ne renomme que si l'ancienne colonne est là
                // ET que la nouvelle ne l'est pas encore (rejeu, ou base déjà
                // corrigée manuellement).
                if (! schemaHasColumn($table, $from) || schemaHasColumn($table, $to)) {
                    continue;
                }

                DB::statement(sprintf(
                    'ALTER TABLE %s RENAME COLUMN %s TO %s',
                    $this->quoteIdentifier($table),
                    $this->quoteIdentifier($from),
                    $this->quoteIdentifier($to),
                ));
            }
        }
    }

    /**
     * Les noms de tables/colonnes proviennent d'une constante du code, jamais
     * d'une entrée utilisateur — on échappe malgré tout par principe.
     */
    private function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
};
