<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * #7420 — Aligne le schéma des annonces sur le contrat applicatif.
 *
 * Deux migrations créent les MÊMES tables avec des colonnes DIFFÉRENTES :
 *
 *   2026_08_30_000017_6110_create_travel_advert_tables.php
 *       travel_advert_types     → code, NAME, is_active
 *       travel_advert_positions → code, NAME, is_active
 *       travel_advert_prices    → TYPE_ID, POSITION_ID, price_per_*
 *
 *   2026_08_30_000921_6108_create_travel_advert_reference_tables.php
 *       travel_advert_types     → code, LABEL
 *       travel_advert_positions → code, LABEL
 *
 * Toutes sont gardées par `schemaTableExists(...)`. `000017` ayant un
 * horodatage antérieur, c'est ELLE qui crée les tables et `000921` devient un
 * no-op silencieux : le schéma réel porte `name` / `type_id` / `position_id`.
 *
 * Or tout le code (modèles, relations, contrôleurs, tests) consomme
 * `label` / `advert_type_id` / `advert_position_id`. Sur une base fraîchement
 * migrée, le premier `POST /api/v1/travel/advert-types` répondait donc 500
 * « column "label" of relation "travel_advert_types" does not exist », et les
 * suites `TravelAdvert*Test` échouaient toutes en setUp (QueryException 25P02
 * en cascade, la transaction PostgreSQL avortée masquant l'erreur réelle) —
 * c'est le défaut de fond listé en #7420 §2.
 *
 * Correctif ADDITIF et IDEMPOTENT (sûr sur un environnement déjà déployé) :
 * les colonnes divergentes sont renommées vers le nom attendu par le code, et
 * uniquement lorsque l'ancienne existe et que la nouvelle n'existe pas encore.
 * PostgreSQL renomme automatiquement les index et contraintes associés.
 */
return new class extends Migration
{
    /**
     * Colonnes à aligner : [table => [[ancien nom, nouveau nom], ...]].
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
                // Idempotent : on ne renomme que si l'ancienne colonne est
                // présente ET que la nouvelle ne l'est pas encore (rejeu, base
                // déjà corrigée manuellement, ou migration homologue d'une
                // autre branche déjà appliquée).
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
     * d'une entrée utilisateur — échappement par principe.
     */
    private function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
};
