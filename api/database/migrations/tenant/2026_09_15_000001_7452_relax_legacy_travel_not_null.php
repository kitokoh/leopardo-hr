<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Issue #7452 — consolidation Travel : une table, une migration (2e temps).
 *
 * Les 28 tables `travel_*` déclarées par 2 à 3 migrations divergentes ont été
 * ramenées à **une seule** déclaration `Schema::create` (la première exécutée,
 * qui gagne de toute façon : les suivantes étaient des no-op silencieux sous
 * `schemaTableExists()`). Les générations devenues perdantes ont été converties
 * en `Schema::table` idempotents qui rattrapent leurs colonnes manquantes
 * (`schemaHasColumn()`), de sorte que le schéma réel soit l'**union** des
 * générations — c'est ce que le code et les tests attendent.
 *
 * Reste le cas des colonnes que **seule la 1re génération** déclare, en
 * `NOT NULL` sans défaut, et que le code (écrit contre la génération récente)
 * ne renseigne jamais : chaque insertion échoue en
 * `null value in column "x" … violates not-null constraint` (13 occurrences
 * pour `travel_loyalty_accounts.contact_identifier`, 12 pour
 * `travel_currency_rates.base_currency`, …). Ces colonnes sont de la **dette de
 * schéma** (pair `base_currency/quote_currency/rate` remplacé par
 * `from_currency/to_currency/rate_minor`, `contact_identifier` remplacé par
 * `contact_id` — voir #7445, `stars` remplacé par `rating`, …).
 *
 * On ne supprime pas ces colonnes (des données existent en dev/prod et un
 * `DROP COLUMN` est destructif) : on **relâche la contrainte NOT NULL**
 * (`DROP NOT NULL` est idempotent et sans perte). La colonne reste disponible
 * pour d'éventuels lecteurs legacy, mais son absence de valeur ne bloque plus
 * une écriture produite par la génération courante.
 *
 * Référence : `docs/audits/MIGRATIONS_DUPLIQUEES_TENANT.md`,
 * garde `dev-hub/tools/check-duplicate-schema-create.py`.
 *
 * @see https://github.com/kitokoh/leopardo-hr/issues/7452
 */
return new class extends Migration
{
    /**
     * Colonnes déclarées uniquement par la 1re génération d'une table dupliquée,
     * en NOT NULL sans défaut, jamais renseignées par le code courant.
     *
     * @var array<string, list<string>>
     */
    private const LEGACY_NOT_NULL_COLUMNS = [
        // 000017 (type_id/position_id) vs 000922/001545 (advert_type_id/advert_position_id).
        'travel_advert_prices' => ['type_id', 'position_id'],
        // 000921/001544 déclarent aussi `name` en NOT NULL ; le code n'écrit que `label`.
        'travel_advert_types' => ['name'],
        'travel_advert_positions' => ['name'],
        // 000013 (code) vs 000916 (slug) — le code écrit slug.
        'travel_article_categories' => ['code'],
        // 000014 (body/author_name) vs 000917 (content_redacted/author_id).
        'travel_comments' => ['body'],
        // 000020 (base_currency/quote_currency/rate) vs 001506 (from_currency/to_currency/rate_minor).
        'travel_currency_rates' => ['base_currency', 'quote_currency', 'rate'],
        // 000915 (read model par trajet) vs 001503 (read model par jour/source/statut).
        'travel_daily_sales' => ['trip_id'],
        // 000012 (contact_identifier) vs 001510 (contact_id) — volet schéma de #7445.
        'travel_loyalty_accounts' => ['contact_identifier'],
        // 000016 (answers_redacted/participant_identifier) vs 000920/000923.
        'travel_quiz_participations' => ['answers_redacted', 'participant_identifier'],
        // 000016 (choices/correct_answer_hash/label/rank) vs 000920/000923.
        'travel_quiz_questions' => ['choices', 'correct_answer_hash', 'label', 'rank'],
        // 000006 (idempotency_key/reference/passenger_count) vs 000019 (guichet entreprise).
        'travel_quotes' => ['idempotency_key', 'passenger_count', 'reference'],
        // 000015 (stars) vs 000917 (rating).
        'travel_ratings' => ['stars'],
        // 000018 la déclare NOT NULL, 000924 la déclare nullable : l'union retient
        // le plus permissif (le code crée des sites sans ville).
        'travel_tourist_sites' => ['city_id'],
    ];

    public function up(): void
    {
        foreach (self::LEGACY_NOT_NULL_COLUMNS as $table => $columns) {
            if (! schemaTableExists($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! schemaHasColumn($table, $column)) {
                    continue;
                }

                // Idempotent : un second DROP NOT NULL sur une colonne déjà nullable
                // est un no-op PostgreSQL.
                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} DROP NOT NULL");
            }
        }
    }

    public function down(): void
    {
        // Pas de retour arrière : remettre NOT NULL exigerait que TOUTES les lignes
        // portent une valeur pour ces colonnes legacy, ce que le schéma actuel ne
        // garantit plus (c'est précisément le défaut corrigé). Un rollback
        // destructif (DROP COLUMN) n'est pas souhaitable : les données existent.
    }
};
