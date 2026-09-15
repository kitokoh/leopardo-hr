<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #7452 — Consolidation du schéma Travel (colonne manquante = « column does not exist »).
 *
 * Le module Travel porte **28 tables déclarées par 2 ou 3 migrations
 * divergentes** (`Schema::create` en double, chacune gardée par
 * `schemaTableExists()`) : la **première en ordre d'exécution gagne** et toutes
 * les suivantes sont des no-op **silencieux**. Quand leurs colonnes divergent,
 * tout le code écrit contre la génération perdante échoue en
 * `column "x" does not exist`, le plus souvent masqué par une cascade
 * `SQLSTATE[25P02]` (« current transaction is aborted ») — mesuré : 223 échecs
 * de `tests/Feature/Travel`.
 *
 * Correctif **forward-only** (aucune réécriture d'historique, aucun second
 * `Schema::create`) :
 *
 *  1. les colonnes attendues par le code et absentes du schéma réel sont
 *     ajoutées par `Schema::table`, gardées par `schemaHasColumn()` ;
 *  2. les colonnes « zombies » de la génération gagnante que le code ne
 *     renseigne **jamais** (ex. `travel_advert_types.name`, remplacé par
 *     `label` — cf. #7417) perdent leur `NOT NULL` : sans cela tout INSERT du
 *     code échoue en `23502`. Elles sont conservées (une suppression ferait
 *     perdre des données) ;
 *  3. les index d'unicité attendus par les tests (`one actor one like`,
 *     `rating unique per actor`) portent sur les colonnes réellement utilisées.
 *
 * Idempotente : rejouable sur une base migrée (Render rejoue des migrations,
 * cf. AGENTS.md « Render et migrations PostgreSQL ») comme sur une base
 * fraîche. `down()` retire uniquement ce que `up()` a ajouté.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Annonces : référentiels types / positions / prix (#7417) ────────
        $this->addColumn('travel_advert_types', 'label', static fn (Blueprint $t) => $t->string('label', 120)->nullable());
        $this->backfillFrom('travel_advert_types', 'label', 'name');
        $this->relaxNotNull('travel_advert_types', ['name']);

        $this->addColumn('travel_advert_positions', 'label', static fn (Blueprint $t) => $t->string('label', 120)->nullable());
        $this->backfillFrom('travel_advert_positions', 'label', 'name');
        $this->relaxNotNull('travel_advert_positions', ['name']);

        $this->addColumn('travel_advert_prices', 'advert_type_id', static fn (Blueprint $t) => $t->unsignedBigInteger('advert_type_id')->nullable());
        $this->addColumn('travel_advert_prices', 'advert_position_id', static fn (Blueprint $t) => $t->unsignedBigInteger('advert_position_id')->nullable());
        $this->relaxNotNull('travel_advert_prices', ['type_id', 'position_id']);
        $this->addUnique('travel_advert_prices', ['company_id', 'advert_type_id', 'advert_position_id'], 'travel_advert_prices_company_advert_pair_unique');

        // ── Articles / catégories (#6104) ──────────────────────────────────
        $this->addColumn('travel_article_categories', 'slug', static fn (Blueprint $t) => $t->string('slug', 80)->nullable());
        $this->relaxNotNull('travel_article_categories', ['code']);
        $this->addUnique('travel_article_categories', ['company_id', 'slug'], 'travel_article_categories_company_slug_unique');

        $this->addColumn('travel_articles', 'slug', static fn (Blueprint $t) => $t->string('slug', 100)->nullable());
        $this->addColumn('travel_articles', 'author_type', static fn (Blueprint $t) => $t->string('author_type', 20)->nullable());
        $this->addColumn('travel_articles', 'author_id', static fn (Blueprint $t) => $t->unsignedBigInteger('author_id')->nullable());
        $this->addColumn('travel_articles', 'moderated_by_user_id', static fn (Blueprint $t) => $t->unsignedBigInteger('moderated_by_user_id')->nullable());
        $this->addColumn('travel_articles', 'moderated_at', static fn (Blueprint $t) => $t->timestamp('moderated_at')->nullable());
        $this->addUnique('travel_articles', ['company_id', 'slug'], 'travel_articles_company_slug_unique');

        // ── Commentaires / engagement (#6105) ─────────────────────────────
        $this->addColumn('travel_comments', 'author_id', static fn (Blueprint $t) => $t->unsignedBigInteger('author_id')->nullable());
        $this->addColumn('travel_comments', 'content_redacted', static fn (Blueprint $t) => $t->string('content_redacted', 2000)->nullable());
        $this->relaxNotNull('travel_comments', ['body']);

        $this->addColumn('travel_likes', 'actor_id', static fn (Blueprint $t) => $t->unsignedBigInteger('actor_id')->nullable());
        $this->addUnique('travel_likes', ['company_id', 'article_id', 'actor_type', 'actor_id'], 'travel_likes_company_article_actor_id_unique');

        $this->addColumn('travel_ratings', 'actor_id', static fn (Blueprint $t) => $t->unsignedBigInteger('actor_id')->nullable());
        $this->addColumn('travel_ratings', 'rating', static fn (Blueprint $t) => $t->unsignedTinyInteger('rating')->nullable());
        $this->relaxNotNull('travel_ratings', ['stars']);
        $this->addUnique('travel_ratings', ['company_id', 'article_id', 'actor_type', 'actor_id'], 'travel_ratings_company_article_actor_id_unique');

        $this->addColumn('travel_shares', 'actor_id', static fn (Blueprint $t) => $t->unsignedBigInteger('actor_id')->nullable());

        // ── Quiz (#6107) ──────────────────────────────────────────────────
        $this->addColumn('travel_quizzes', 'starts_at', static fn (Blueprint $t) => $t->timestampTz('starts_at')->nullable());
        $this->addColumn('travel_quizzes', 'ends_at', static fn (Blueprint $t) => $t->timestampTz('ends_at')->nullable());

        $this->addColumn('travel_quiz_questions', 'question', static fn (Blueprint $t) => $t->string('question', 500)->nullable());
        $this->addColumn('travel_quiz_questions', 'options', static fn (Blueprint $t) => $t->jsonb('options')->nullable());
        $this->addColumn('travel_quiz_questions', 'correct_option_index', static fn (Blueprint $t) => $t->unsignedSmallInteger('correct_option_index')->nullable());
        $this->addColumn('travel_quiz_questions', 'position', static fn (Blueprint $t) => $t->unsignedSmallInteger('position')->default(0));
        $this->relaxNotNull('travel_quiz_questions', ['rank', 'label', 'choices', 'correct_answer_hash']);

        $this->addColumn('travel_quiz_participations', 'participant_contact_id', static fn (Blueprint $t) => $t->unsignedBigInteger('participant_contact_id')->nullable());
        $this->addColumn('travel_quiz_participations', 'participant_email', static fn (Blueprint $t) => $t->string('participant_email', 190)->nullable());
        $this->addColumn('travel_quiz_participations', 'participant_name', static fn (Blueprint $t) => $t->string('participant_name', 160)->nullable());
        $this->addColumn('travel_quiz_participations', 'answers', static fn (Blueprint $t) => $t->jsonb('answers')->nullable());
        $this->addColumn('travel_quiz_participations', 'bonus', static fn (Blueprint $t) => $t->unsignedSmallInteger('bonus')->default(0));
        $this->addColumn('travel_quiz_participations', 'status', static fn (Blueprint $t) => $t->string('status', 20)->default('submitted'));
        $this->relaxNotNull('travel_quiz_participations', ['answers_redacted', 'participant_identifier']);

        // ── Sites touristiques (#6112) ────────────────────────────────────
        $this->addColumn('travel_tourist_sites', 'image_asset_id', static fn (Blueprint $t) => $t->unsignedBigInteger('image_asset_id')->nullable());

        // ── Politiques d'annulation (#6103) ───────────────────────────────
        $this->addColumn('travel_cancellation_policies', 'hours_before_departure', static fn (Blueprint $t) => $t->unsignedInteger('hours_before_departure')->default(0));
        $this->addColumn('travel_cancellation_policies', 'created_by_user_id', static fn (Blueprint $t) => $t->unsignedBigInteger('created_by_user_id')->nullable());

        // ── Taux de change (#6096) ────────────────────────────────────────
        $this->addColumn('travel_currency_rates', 'from_currency', static fn (Blueprint $t) => $t->char('from_currency', 3)->nullable());
        $this->addColumn('travel_currency_rates', 'to_currency', static fn (Blueprint $t) => $t->char('to_currency', 3)->nullable());
        $this->addColumn('travel_currency_rates', 'rate_minor', static fn (Blueprint $t) => $t->unsignedBigInteger('rate_minor')->nullable());
        $this->addColumn('travel_currency_rates', 'valid_to', static fn (Blueprint $t) => $t->date('valid_to')->nullable());
        $this->relaxNotNull('travel_currency_rates', ['base_currency', 'quote_currency', 'rate']);
        $this->addUnique('travel_currency_rates', ['company_id', 'from_currency', 'to_currency', 'valid_from'], 'travel_currency_rates_company_pair_from_unique');
    }

    public function down(): void
    {
        $this->dropColumns('travel_advert_types', ['label']);
        $this->dropColumns('travel_advert_positions', ['label']);
        $this->dropColumns('travel_advert_prices', ['advert_type_id', 'advert_position_id']);
        $this->dropColumns('travel_article_categories', ['slug']);
        $this->dropColumns('travel_articles', ['slug', 'author_type', 'author_id', 'moderated_by_user_id', 'moderated_at']);
        $this->dropColumns('travel_comments', ['author_id', 'content_redacted']);
        $this->dropColumns('travel_likes', ['actor_id']);
        $this->dropColumns('travel_ratings', ['actor_id', 'rating']);
        $this->dropColumns('travel_shares', ['actor_id']);
        $this->dropColumns('travel_quizzes', ['starts_at', 'ends_at']);
        $this->dropColumns('travel_quiz_questions', ['question', 'options', 'correct_option_index', 'position']);
        $this->dropColumns('travel_quiz_participations', ['participant_contact_id', 'participant_email', 'participant_name', 'answers', 'bonus', 'status']);
        $this->dropColumns('travel_tourist_sites', ['image_asset_id']);
        $this->dropColumns('travel_cancellation_policies', ['hours_before_departure', 'created_by_user_id']);
        $this->dropColumns('travel_currency_rates', ['from_currency', 'to_currency', 'rate_minor', 'valid_to']);

        foreach ([
            'travel_advert_prices_company_advert_pair_unique',
            'travel_article_categories_company_slug_unique',
            'travel_articles_company_slug_unique',
            'travel_likes_company_article_actor_id_unique',
            'travel_ratings_company_article_actor_id_unique',
            'travel_currency_rates_company_pair_from_unique',
        ] as $index) {
            DB::statement('DROP INDEX IF EXISTS '.$index);
        }
    }

    /** Ajoute une colonne si la table existe et que la colonne manque (idempotent). */
    private function addColumn(string $table, string $column, callable $definition): void
    {
        if (! schemaTableExists($table) || schemaHasColumn($table, $column)) {
            return;
        }

        Schema::table($table, static function (Blueprint $blueprint) use ($definition): void {
            $definition($blueprint);
        });
    }

    /**
     * Retire le `NOT NULL` d'une colonne « zombie » de la génération gagnante :
     * le code ne la renseigne jamais, l'INSERT échouerait en 23502. La colonne
     * est conservée (aucune donnée perdue).
     *
     * @param  list<string>  $columns
     */
    private function relaxNotNull(string $table, array $columns): void
    {
        if (! schemaTableExists($table)) {
            return;
        }

        foreach ($columns as $column) {
            if (! schemaHasColumn($table, $column)) {
                continue;
            }

            DB::statement(sprintf('ALTER TABLE %s ALTER COLUMN %s DROP NOT NULL', $table, $column));
        }
    }

    /**
     * Recopie une colonne historique dans la colonne canonique attendue par le
     * code, pour les lignes déjà écrites (base migrée avant ce correctif).
     */
    private function backfillFrom(string $table, string $target, string $source): void
    {
        if (! schemaTableExists($table) || ! schemaHasColumn($table, $target) || ! schemaHasColumn($table, $source)) {
            return;
        }

        DB::statement(sprintf('UPDATE %s SET %s = %s WHERE %s IS NULL', $table, $target, $source, $target));
    }

    /**
     * @param  list<string>  $columns
     */
    private function addUnique(string $table, array $columns, string $name): void
    {
        if (! schemaTableExists($table) || $this->indexExists($name)) {
            return;
        }

        Schema::table($table, static function (Blueprint $blueprint) use ($columns, $name): void {
            $blueprint->unique($columns, $name);
        });
    }

    /**
     * @param  list<string>  $columns
     */
    private function dropColumns(string $table, array $columns): void
    {
        if (! schemaTableExists($table)) {
            return;
        }

        $present = array_values(array_filter($columns, static fn (string $column): bool => schemaHasColumn($table, $column)));

        if ($present === []) {
            return;
        }

        Schema::table($table, static function (Blueprint $blueprint) use ($present): void {
            $blueprint->dropColumn($present);
        });
    }

    private function indexExists(string $name): bool
    {
        return DB::selectOne('SELECT 1 FROM pg_indexes WHERE indexname = ?', [$name]) !== null;
    }
};
