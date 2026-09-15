<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #7452 — Réconciliation du schéma TravelAgency, **strictement additive**.
 *
 * Contexte mesuré (`docs/audits/MIGRATIONS_DUPLIQUEES_TENANT.md`) : 68 tables
 * sont déclarées par 2 à 4 migrations, 36 avec des colonnes divergentes. Chaque
 * `Schema::create` étant gardé par `if (! schemaTableExists('<table>'))`, **la
 * première migration exécutée gagne** et toutes les suivantes sont des no-op
 * silencieux. Quand le code a été écrit contre la génération qui a perdu, la
 * requête casse en `column "x" does not exist`, la transaction est avortée, et
 * la cascade `25P02` masque l'erreur d'origine — d'où 223 échecs de
 * `tests/Feature/Travel` sans cause lisible.
 *
 * ## Pourquoi additif et non renommant — c'est le point important
 *
 * Le réflexe naturel est de renommer la colonne gagnante vers le nom attendu
 * par le code (`name` → `label`). **C'est faux dans ce dépôt** : les deux
 * générations sont encore vivantes *à l'intérieur du même module*. Sur
 * `travel_article_categories` par exemple, `TravelArticleCategoryFactory` écrit
 * `code` (génération gagnante) tandis que `TravelArticle` (le modèle) écrit
 * `slug` (génération perdante) ; sur `travel_cancellation_policies`, le
 * Resource lit `cancel_before_hours` pendant que le Resolver lit
 * `hours_before_departure`. Un renommage casse donc nécessairement une moitié
 * du module — vérifié en pratique : le renommage de `code` → `slug` faisait
 * tomber `TravelEngagementApiTest` en cascade sur la simple création d'un
 * article.
 *
 * Cette migration prend donc le seul parti qui ne peut rien casser :
 *
 *  1. `add` — la colonne attendue par le code n'existe pas du tout : on
 *     l'ajoute, **nullable ou avec défaut** (jamais `NOT NULL` sans défaut :
 *     sur une base déjà peuplée, ajouter une colonne obligatoire échoue).
 *  2. `relax` — la colonne existe, mais `NOT NULL`, et le code ne l'alimente
 *     pas (il écrit la variante d'à côté) : on relâche la contrainte. On ne
 *     peut pas inventer une valeur, donc on ne va jamais dans l'autre sens.
 *
 * Aucun renommage, aucune suppression, aucune contrainte ajoutée : les deux
 * moitiés du module continuent de fonctionner, et un environnement déjà
 * déployé n'est pas cassé (les anciennes colonnes restent, simplement
 * facultatives pour celles que le code n'écrit plus).
 *
 * La consolidation « une table = une migration » (retirer les `Schema::create`
 * concurrents) est un travail de dépôt, séparé : elle ne change pas le schéma
 * des bases existantes et relève de la garde
 * `migration-duplication-guard.yml` + des tranches par module.
 *
 * Portée : `TravelAgency` uniquement (#7452 est explicitement « module par
 * module »). EduManager (#7410), FuelStation, Delivery, Edge et les tables
 * `audit_logs`/`edge_nodes` restent à traiter dans leurs propres tranches.
 */
return new class extends Migration
{
    /**
     * Colonnes à ajouter, avec la définition de la génération suivie par le code.
     * Toutes sont facultatives (nullable, ou avec défaut) — jamais obligatoires.
     *
     * @var array<string, array<string, array{0: string, 1: array<string, mixed>}>>
     */
    private const ADDITIONS = [
        // #6107 — quiz. La génération gagnante pose la variante « hash de
        // réponse » (`rank`/`label`/`choices`/`correct_answer_hash`) ; le code
        // consomme la variante par index (`position`/`question`/`options`/
        // `correct_option_index`).
        'travel_quiz_questions' => [
            'question' => ['string', ['length' => 500, 'nullable' => true]],
            'options' => ['json', ['nullable' => true]],
            'correct_option_index' => ['unsignedTinyInteger', ['nullable' => true]],
            'position' => ['unsignedInteger', ['nullable' => true]],
        ],
        'travel_quiz_participations' => [
            'participant_email' => ['string', ['length' => 255, 'nullable' => true]],
            'participant_contact_id' => ['unsignedBigInteger', ['nullable' => true]],
            'participant_name' => ['string', ['length' => 160, 'nullable' => true]],
            'answers' => ['json', ['nullable' => true]],
            'bonus' => ['unsignedInteger', ['default' => 0]],
            'status' => ['string', ['length' => 20, 'default' => 'completed']],
        ],
        // #6107 — fenêtre de publication du quiz (le code l'expose et la teste).
        'travel_quizzes' => [
            'starts_at' => ['timestampTz', ['nullable' => true]],
            'ends_at' => ['timestampTz', ['nullable' => true]],
        ],
        // #6103 — politique d'annulation : le code (modèle, contrôleur,
        // `TravelRefundPolicyResolver`) consomme `hours_before_departure`, la
        // colonne réelle est `cancel_before_hours` (conservée : le Resource et
        // le Service la lisent encore).
        'travel_cancellation_policies' => [
            'hours_before_departure' => ['unsignedInteger', ['nullable' => true]],
            'created_by_user_id' => ['unsignedBigInteger', ['nullable' => true]],
        ],
        // #6104 — articles : `slug`, type d'auteur et champs de modération
        // consommés par le code (`author_user_id`, la variante gagnante, reste
        // en place pour les factories qui l'utilisent).
        'travel_articles' => [
            'slug' => ['string', ['length' => 200, 'nullable' => true]],
            'author_type' => ['string', ['length' => 20, 'nullable' => true]],
            'author_id' => ['unsignedBigInteger', ['nullable' => true]],
            'moderated_by_user_id' => ['unsignedBigInteger', ['nullable' => true]],
            'moderated_at' => ['timestamp', ['nullable' => true]],
        ],
        'travel_article_categories' => [
            'slug' => ['string', ['length' => 200, 'nullable' => true]],
        ],
        // #6105 — commentaires : le code consomme `content_redacted`/`author_id`
        // (la colonne réelle est `body`/`author_user_id`, conservées).
        'travel_comments' => [
            'content_redacted' => ['text', ['nullable' => true]],
            'author_id' => ['unsignedBigInteger', ['nullable' => true]],
        ],
        // #6106 — engagement : le code consomme `actor_id` (et `rating` pour
        // les notes, la colonne réelle étant `stars`).
        'travel_likes' => [
            'actor_id' => ['unsignedBigInteger', ['nullable' => true]],
        ],
        'travel_ratings' => [
            'actor_id' => ['unsignedBigInteger', ['nullable' => true]],
            'rating' => ['unsignedTinyInteger', ['nullable' => true]],
        ],
        'travel_shares' => [
            'actor_id' => ['unsignedBigInteger', ['nullable' => true]],
        ],
        // #6112 — rattachement de l'image (le code expose `image_asset_id`, la
        // génération gagnante porte une colonne `images`).
        'travel_tourist_sites' => [
            'image_asset_id' => ['unsignedBigInteger', ['nullable' => true]],
        ],
        // #6094 — variante « compte entreprise » du devis, consommée par le
        // chemin corporate.
        'travel_quotes' => [
            'corporate_account_id' => ['unsignedBigInteger', ['nullable' => true]],
            'class_id' => ['unsignedBigInteger', ['nullable' => true]],
            'passengers_count' => ['unsignedInteger', ['nullable' => true]],
        ],
        // #6076 — modèle de lecture d'occupation : le code ventile les sièges.
        'travel_trip_occupancy' => [
            'sold_seats' => ['unsignedInteger', ['default' => 0]],
            'reserved_seats' => ['unsignedInteger', ['default' => 0]],
            'free_seats' => ['unsignedInteger', ['default' => 0]],
        ],
        // #6096 — taux de conversion : `TravelCurrencyRate` et
        // `UpsertCurrencyRateAction` consomment `from_currency`/`to_currency`/
        // `rate_minor`/`valid_to`, là où le schéma réel porte
        // `base_currency`/`quote_currency`/`rate`/`valid_until` (conservées :
        // aucun code vivant ne les lit, mais on ne supprime rien).
        'travel_currency_rates' => [
            'from_currency' => ['char', ['length' => 3, 'nullable' => true]],
            'to_currency' => ['char', ['length' => 3, 'nullable' => true]],
            'rate_minor' => ['unsignedBigInteger', ['nullable' => true]],
            'valid_to' => ['date', ['nullable' => true]],
        ],
    ];

    /**
     * Colonnes existantes mais `NOT NULL` que le code n'alimente pas : elles
     * bloqueraient chaque écriture de la génération vivante. On relâche la
     * contrainte — jamais l'inverse (on ne peut pas inventer une valeur).
     *
     * @var array<string, array<int, string>>
     */
    private const RELAX = [
        // Le code écrit `question`/`options`/`correct_option_index`/`position`.
        'travel_quiz_questions' => ['rank', 'label', 'choices', 'correct_answer_hash'],
        // Le code écrit `participant_email`/`answers`.
        'travel_quiz_participations' => ['participant_identifier', 'answers_redacted'],
        // Le code écrit `content_redacted`.
        'travel_comments' => ['body'],
        // Le code écrit `rating`, la colonne réelle est `stars`.
        'travel_ratings' => ['stars'],
        // Le code écrit `slug`, la colonne réelle est `code`.
        'travel_article_categories' => ['code'],
        // Le code écrit `from_currency`/`to_currency`/`rate_minor`/`valid_to`.
        'travel_currency_rates' => ['base_currency', 'quote_currency', 'rate', 'valid_from'],
        // Le modèle de lecture est reconstruit au niveau du jour, sans trajet.
        'travel_daily_sales' => ['trip_id'],
        // Devis « entreprise » : `passenger_count` n'est pas renseigné
        // (c'est `passengers_count`).
        'travel_quotes' => ['passenger_count'],
    ];

    public function up(): void
    {
        $this->relaxNotNulColumns(self::RELAX);
        $this->addColumns(self::ADDITIONS);
    }

    public function down(): void
    {
        // Réversibilité volontairement partielle : on retire ce que cette
        // migration a AJOUTÉ. Les `NOT NULL` relâchés ne sont PAS rétablis —
        // des lignes écrites depuis pourraient les violer, et un `down()` qui
        // échoue est pire qu'un `down()` incomplet (le rétablissement est un
        // `ALTER TABLE ... SET NOT NULL` à rejouer à la main, en connaissance
        // de cause).
        $this->dropAddedColumns(self::ADDITIONS);
    }

    /**
     * @param  array<string, array<string, array{0: string, 1: array<string, mixed>}>>  $map
     */
    private function addColumns(array $map): void
    {
        foreach ($map as $table => $columns) {
            if (! schemaTableExists($table)) {
                continue;
            }

            $missing = [];

            foreach ($columns as $column => [$type, $options]) {
                if (schemaHasColumn($table, $column)) {
                    continue;
                }

                $missing[$column] = [$type, $options];
            }

            if ($missing === []) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($missing): void {
                foreach ($missing as $column => [$type, $options]) {
                    $definition = match ($type) {
                        'string' => $blueprint->string($column, $options['length'] ?? 255),
                        'char' => $blueprint->char($column, $options['length'] ?? 255),
                        default => $blueprint->{$type}($column),
                    };

                    if (($options['nullable'] ?? false) === true) {
                        $definition->nullable();
                    }

                    if (array_key_exists('default', $options)) {
                        $definition->default($options['default']);
                    }
                }
            });
        }
    }

    /**
     * @param  array<string, array<string, array{0: string, 1: array<string, mixed>}>>  $map
     */
    private function dropAddedColumns(array $map): void
    {
        foreach ($map as $table => $columns) {
            if (! schemaTableExists($table)) {
                continue;
            }

            foreach (array_keys($columns) as $column) {
                if (schemaHasColumn($table, $column)) {
                    Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                        $blueprint->dropColumn($column);
                    });
                }
            }
        }
    }

    /**
     * @param  array<string, array<int, string>>  $map
     */
    private function relaxNotNulColumns(array $map): void
    {
        foreach ($map as $table => $columns) {
            if (! schemaTableExists($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! schemaHasColumn($table, $column) || $this->isNullable($table, $column)) {
                    continue;
                }

                DB::statement(sprintf(
                    'ALTER TABLE %s ALTER COLUMN %s DROP NOT NULL',
                    $this->quote($table),
                    $this->quote($column),
                ));
            }
        }
    }

    private function isNullable(string $table, string $column): bool
    {
        $type = DB::selectOne(
            'SELECT is_nullable FROM information_schema.columns WHERE table_name = ? AND column_name = ?',
            [$table, $column],
        );

        return $type !== null && ($type->is_nullable ?? 'NO') === 'YES';
    }

    /**
     * Les identifiants proviennent de constantes du code, jamais d'une entrée
     * utilisateur — on échappe malgré tout par principe.
     */
    private function quote(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
};
