<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GSI-4 → GSI-7 (#8515, #8516, #8517, #8518) — alignement du schéma sur les
 * workflows éditoriaux et sur l'exigence de provenance.
 *
 * Cette migration est ADDITIVE et REJOUABLE (chaque colonne est ajoutée sous
 * `hasColumn`, chaque contrainte est recréée sous `DROP IF EXISTS`) : elle
 * s'applique sur un schéma GSI-1/GSI-2 déjà en place.
 *
 * Trois invariants sont matérialisés ici, en base, et non seulement en code :
 *
 *  1. **AEO** — `answer_approved` est un FIGÉ pris à la validation : la
 *     réponse publiée ne peut plus dériver de ce qui a été relu.
 *  2. **GEO** — la méthode de preuve est obligatoire et vaut
 *     `spot_test | observed_data | measured_indicator`. Un test ponctuel ne
 *     peut pas être présenté comme un indicateur mesuré : c'est une contrainte
 *     de valeur, pas une convention de rédaction.
 *  3. **Contenu & concurrence** — `published` exige une URL ; toute note
 *     tarifaire exige au moins une source datée.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->extendAeoQuestions();
        $this->extendGeoChecks();
        $this->extendGeoQueries();
        $this->extendContentItems();
        $this->extendCompetitors();
    }

    /** GSI-4 (#8515) — bibliothèque AEO avec révision humaine obligatoire. */
    private function extendAeoQuestions(): void
    {
        if (! Schema::hasTable('aeo_questions')) {
            return;
        }

        Schema::table('aeo_questions', function (Blueprint $table): void {
            if (! Schema::hasColumn('aeo_questions', 'sector')) {
                $table->string('sector', 40)->nullable();
            }
            if (! Schema::hasColumn('aeo_questions', 'sources')) {
                // Liste de sources (URL) ayant servi à rédiger la réponse.
                $table->jsonb('sources')->nullable();
            }
            if (! Schema::hasColumn('aeo_questions', 'answer_draft')) {
                $table->text('answer_draft')->nullable();
            }
            // FIGÉ au moment de la validation : c'est LUI qui est publié.
            if (! Schema::hasColumn('aeo_questions', 'answer_approved')) {
                $table->text('answer_approved')->nullable();
            }
            if (! Schema::hasColumn('aeo_questions', 'approved_by')) {
                $table->foreignId('approved_by')->nullable();
            }
            if (! Schema::hasColumn('aeo_questions', 'approved_at')) {
                $table->timestamp('approved_at')->nullable();
            }
        });

        // Nouveau jeu de statuts : draft → in_review → approved → published,
        // plus to_update (le contenu a vieilli) et rejected.
        DB::statement('ALTER TABLE aeo_questions DROP CONSTRAINT IF EXISTS aeo_questions_status_check');
        DB::statement(
            "ALTER TABLE aeo_questions ADD CONSTRAINT aeo_questions_status_check "
            ."CHECK (status IN ('draft', 'in_review', 'approved', 'published', 'to_update', 'rejected'))"
        );
        DB::statement("ALTER TABLE aeo_questions ALTER COLUMN status SET DEFAULT 'draft'");

        // Publication conditionnée à une validation humaine ET à une réponse
        // figée : sans `answer_approved`, il n'y a rien de relu à publier.
        DB::statement('ALTER TABLE aeo_questions DROP CONSTRAINT IF EXISTS aeo_questions_publish_requires_human_review');
        DB::statement(
            "ALTER TABLE aeo_questions ADD CONSTRAINT aeo_questions_publish_requires_human_review "
            ."CHECK (status <> 'published' OR (approved_by IS NOT NULL AND approved_at IS NOT NULL AND answer_approved IS NOT NULL))"
        );
    }

    /** GSI-5 (#8516) — checks GEO avec méthode de preuve explicite. */
    private function extendGeoChecks(): void
    {
        if (! Schema::hasTable('geo_checks')) {
            return;
        }

        Schema::table('geo_checks', function (Blueprint $table): void {
            if (! Schema::hasColumn('geo_checks', 'leopardo_mentioned')) {
                $table->boolean('leopardo_mentioned')->default(false);
            }
            if (! Schema::hasColumn('geo_checks', 'citation_url')) {
                $table->text('citation_url')->nullable();
            }
            if (! Schema::hasColumn('geo_checks', 'observed_text')) {
                $table->text('observed_text')->nullable();
            }
            if (! Schema::hasColumn('geo_checks', 'sources')) {
                $table->jsonb('sources')->nullable();
            }
            if (! Schema::hasColumn('geo_checks', 'checked_by')) {
                $table->foreignId('checked_by')->nullable();
            }
        });

        // Trois niveaux de preuve, du plus faible au plus fort. La contrainte
        // interdit d'inventer un niveau hors de ces trois-là.
        DB::statement('ALTER TABLE geo_checks DROP CONSTRAINT IF EXISTS geo_checks_method_check');
        DB::statement(
            "ALTER TABLE geo_checks ADD CONSTRAINT geo_checks_method_check "
            ."CHECK (method IN ('spot_test', 'observed_data', 'measured_indicator'))"
        );
        DB::statement("ALTER TABLE geo_checks ALTER COLUMN method SET DEFAULT 'spot_test'");

        // Un check manuel doit porter sa preuve : `evidence` OU `observed_text`.
        DB::statement('ALTER TABLE geo_checks DROP CONSTRAINT IF EXISTS geo_checks_manual_requires_evidence');
        DB::statement(
            "ALTER TABLE geo_checks ADD CONSTRAINT geo_checks_manual_requires_evidence "
            ."CHECK (method <> 'spot_test' OR evidence IS NOT NULL OR observed_text IS NOT NULL)"
        );

        // Le moteur « claude » est explicitement demandé par la spec.
        DB::statement('ALTER TABLE geo_checks DROP CONSTRAINT IF EXISTS geo_checks_engine_check');
        DB::statement(
            "ALTER TABLE geo_checks ADD CONSTRAINT geo_checks_engine_check "
            ."CHECK (engine IN ('chatgpt', 'claude', 'gemini', 'perplexity', 'copilot', 'other'))"
        );
    }

    /** GSI-5 (#8516) — pages de référence d'une requête stratégique. */
    private function extendGeoQueries(): void
    {
        if (! Schema::hasTable('geo_queries')) {
            return;
        }

        Schema::table('geo_queries', function (Blueprint $table): void {
            if (! Schema::hasColumn('geo_queries', 'sector')) {
                $table->string('sector', 40)->nullable();
            }
            if (! Schema::hasColumn('geo_queries', 'reference_pages')) {
                $table->jsonb('reference_pages')->nullable();
            }
        });
    }

    /** GSI-6 (#8517) — contenu éditorial à statuts contrôlés. */
    private function extendContentItems(): void
    {
        if (! Schema::hasTable('content_items')) {
            return;
        }

        Schema::table('content_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('content_items', 'sector')) {
                $table->string('sector', 40)->nullable();
            }
            if (! Schema::hasColumn('content_items', 'target_url')) {
                $table->text('target_url')->nullable();
            }
            if (! Schema::hasColumn('content_items', 'scheduled_at')) {
                $table->timestamp('scheduled_at')->nullable();
            }
            if (! Schema::hasColumn('content_items', 'approved_by')) {
                $table->foreignId('approved_by')->nullable();
            }
            if (! Schema::hasColumn('content_items', 'approved_at')) {
                $table->timestamp('approved_at')->nullable();
            }
        });

        // Corpus de types demandé par la spec (le précédent était plus étroit).
        DB::statement('ALTER TABLE content_items DROP CONSTRAINT IF EXISTS content_items_kind_check');
        DB::statement(
            "ALTER TABLE content_items ADD CONSTRAINT content_items_kind_check "
            ."CHECK (kind IN ('landing', 'blog', 'guide', 'faq', 'comparison', 'social', 'video_script', 'cta'))"
        );

        DB::statement('ALTER TABLE content_items DROP CONSTRAINT IF EXISTS content_items_status_check');
        DB::statement(
            "ALTER TABLE content_items ADD CONSTRAINT content_items_status_check "
            ."CHECK (status IN ('idea', 'draft', 'review', 'approved', 'published', 'to_update'))"
        );

        // `published` exige l'approbation ET une URL : publier sans URL, c'est
        // publier nulle part.
        DB::statement('ALTER TABLE content_items DROP CONSTRAINT IF EXISTS content_items_publish_requires_author_and_date');
        DB::statement(
            "ALTER TABLE content_items ADD CONSTRAINT content_items_publish_requires_author_and_date "
            ."CHECK (status <> 'published' OR (approved_at IS NOT NULL AND target_url IS NOT NULL))"
        );
    }

    /** GSI-7 (#8518) — concurrence avec sources datées obligatoires. */
    private function extendCompetitors(): void
    {
        if (! Schema::hasTable('competitors')) {
            return;
        }

        Schema::table('competitors', function (Blueprint $table): void {
            if (! Schema::hasColumn('competitors', 'positioning')) {
                $table->text('positioning')->nullable();
            }
            if (! Schema::hasColumn('competitors', 'value_proposition')) {
                $table->text('value_proposition')->nullable();
            }
            if (! Schema::hasColumn('competitors', 'public_features')) {
                $table->jsonb('public_features')->nullable();
            }
            if (! Schema::hasColumn('competitors', 'pricing_notes')) {
                $table->text('pricing_notes')->nullable();
            }
            if (! Schema::hasColumn('competitors', 'pricing_sources')) {
                // [{url, label, observed_at}] — obligatoire dès qu'il y a des
                // notes de tarification.
                $table->jsonb('pricing_sources')->nullable();
            }
            if (! Schema::hasColumn('competitors', 'last_reviewed_at')) {
                $table->timestamp('last_reviewed_at')->nullable();
            }
        });

        // Toute affirmation tarifaire doit être sourcée : la contrainte rend
        // impossible d'enregistrer un prix sans dire d'où il vient.
        DB::statement('ALTER TABLE competitors DROP CONSTRAINT IF EXISTS competitors_pricing_requires_sources');
        DB::statement(
            "ALTER TABLE competitors ADD CONSTRAINT competitors_pricing_requires_sources "
            ."CHECK (pricing_notes IS NULL OR (pricing_sources IS NOT NULL AND jsonb_array_length(pricing_sources) > 0))"
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('competitors')) {
            DB::statement('ALTER TABLE competitors DROP CONSTRAINT IF EXISTS competitors_pricing_requires_sources');
            Schema::table('competitors', function (Blueprint $table): void {
                foreach (['last_reviewed_at', 'pricing_sources', 'pricing_notes', 'public_features', 'value_proposition', 'positioning'] as $c) {
                    if (Schema::hasColumn('competitors', $c)) {
                        $table->dropColumn($c);
                    }
                }
            });
        }

        if (Schema::hasTable('content_items')) {
            DB::statement('ALTER TABLE content_items DROP CONSTRAINT IF EXISTS content_items_status_check');
            DB::statement('ALTER TABLE content_items DROP CONSTRAINT IF EXISTS content_items_kind_check');
            Schema::table('content_items', function (Blueprint $table): void {
                foreach (['approved_at', 'approved_by', 'scheduled_at', 'target_url', 'sector'] as $c) {
                    if (Schema::hasColumn('content_items', $c)) {
                        $table->dropColumn($c);
                    }
                }
            });
        }

        if (Schema::hasTable('geo_queries')) {
            Schema::table('geo_queries', function (Blueprint $table): void {
                foreach (['reference_pages', 'sector'] as $c) {
                    if (Schema::hasColumn('geo_queries', $c)) {
                        $table->dropColumn($c);
                    }
                }
            });
        }

        if (Schema::hasTable('geo_checks')) {
            DB::statement('ALTER TABLE geo_checks DROP CONSTRAINT IF EXISTS geo_checks_engine_check');
            DB::statement('ALTER TABLE geo_checks DROP CONSTRAINT IF EXISTS geo_checks_method_check');
            Schema::table('geo_checks', function (Blueprint $table): void {
                foreach (['checked_by', 'sources', 'observed_text', 'citation_url', 'leopardo_mentioned'] as $c) {
                    if (Schema::hasColumn('geo_checks', $c)) {
                        $table->dropColumn($c);
                    }
                }
            });
        }

        if (Schema::hasTable('aeo_questions')) {
            DB::statement('ALTER TABLE aeo_questions DROP CONSTRAINT IF EXISTS aeo_questions_publish_requires_human_review');
            DB::statement('ALTER TABLE aeo_questions DROP CONSTRAINT IF EXISTS aeo_questions_status_check');
            Schema::table('aeo_questions', function (Blueprint $table): void {
                foreach (['approved_at', 'approved_by', 'answer_approved', 'answer_draft', 'sources', 'sector'] as $c) {
                    if (Schema::hasColumn('aeo_questions', $c)) {
                        $table->dropColumn($c);
                    }
                }
            });
        }
    }
};
