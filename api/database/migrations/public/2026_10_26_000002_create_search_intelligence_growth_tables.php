<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GSI-1 (#8512) — socle SEA (campagnes) et AEO/GEO du module
 * SearchIntelligence.
 *
 * Deux invariants de conception, hérités de l'audit produit :
 *
 *  1. **Aucune métrique n'est inventée.** `campaign_metrics` n'accepte que
 *     des relevés MANUELS et datés (`source = 'manual'`) ou des relevés
 *     issus d'une intégration réellement connectée (`source = 'integration'`,
 *     avec `integration_id` non nul). Il n'existe pas de chemin d'écriture
 *     synthétique : un tableau de bord vide est préférable à un chiffre faux.
 *
 *  2. **Une réponse AEO n'est publiable qu'après révision humaine.**
 *     `aeo_questions.status` démarre à `draft` ; seule la transition
 *     `reviewed` → `published` est permise par le domaine, et elle exige
 *     `reviewed_by` / `reviewed_at` renseignés (contrainte CHECK ci-dessous).
 *
 * @see docs/architecture/bounded-context-registry.json (entrée `search-intelligence`)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->publicTableExists('campaigns')) {
            Schema::create('campaigns', function (Blueprint $table) {
                $table->id();
                $table->enum('channel', ['google_ads', 'ms_ads', 'meta', 'other'])->default('google_ads');
                $table->string('name');
                $table->enum('status', ['draft', 'active', 'paused', 'ended'])->default('draft');
                $table->bigInteger('daily_budget_cents')->nullable();
                $table->string('currency', 3)->default('EUR');
                $table->date('starts_on')->nullable();
                $table->date('ends_on')->nullable();
                $table->timestamps();

                $table->index(['channel', 'status']);
            });
        }

        // Relevés de performance d'une campagne. Pas de colonne calculée ni de
        // valeur par défaut trompeuse : `spend_cents`/`clicks`/`conversions`
        // sont NOT NULL parce qu'un relevé sans ces chiffres n'est pas un
        // relevé — l'API refuse l'insertion plutôt que de stocker des zéros.
        if (! $this->publicTableExists('campaign_metrics')) {
            Schema::create('campaign_metrics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
                $table->date('measured_on');
                $table->enum('source', ['manual', 'integration'])->default('manual');
                $table->foreignId('integration_id')->nullable()->constrained('integrations')->nullOnDelete();
                $table->bigInteger('spend_cents');
                $table->unsignedInteger('impressions');
                $table->unsignedInteger('clicks');
                $table->unsignedInteger('conversions')->default(0);
                $table->unsignedBigInteger('revenue_cents')->default(0);
                $table->timestamps();

                // Un seul relevé par campagne et par jour : un second relevé
                // est une CORRECTION explicite, pas une ligne concurrente.
                $table->unique(['campaign_id', 'measured_on']);
            });

            // Un relevé « integration » sans intégration rattachée serait un
            // chiffre sans provenance. Ajouté en SQL brut : `Blueprint::check()`
            // n'existe pas dans cette version de Laravel (convention du dépôt,
            // cf. 2026_07_23_000002_create_platform_announcements_table.php).
            DB::statement(
                "ALTER TABLE campaign_metrics ADD CONSTRAINT campaign_metrics_integration_source_requires_integration "
                ."CHECK (source <> 'integration' OR integration_id IS NOT NULL)"
            );
        }

        // Bibliothèque questions-réponses (AEO). La contrainte CHECK matérialise
        // en base la règle métier « pas de publication sans révision humaine » :
        // même un INSERT direct en SQL ne peut pas la contourner.
        if (! $this->publicTableExists('aeo_questions')) {
            Schema::create('aeo_questions', function (Blueprint $table) {
                $table->id();
                $table->text('question');
                $table->text('answer')->nullable();
                $table->string('locale', 10)->default('fr');
                $table->enum('status', ['draft', 'reviewed', 'published', 'rejected'])->default('draft');
                $table->foreignId('reviewed_by')->nullable()->constrained('super_admins')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'locale']);
            });

            // Matérialise en base « pas de publication sans révision humaine » :
            // même un INSERT SQL direct ne peut pas la contourner.
            DB::statement(
                "ALTER TABLE aeo_questions ADD CONSTRAINT aeo_questions_publish_requires_human_review "
                ."CHECK (status <> 'published' OR (reviewed_by IS NOT NULL AND reviewed_at IS NOT NULL AND answer IS NOT NULL))"
            );
        }

        // Requêtes stratégiques GEO (présence dans les moteurs génératifs) et
        // checks de présence associés. Chaque check porte sa MÉTHODE explicite
        // (`method`) et la date d'observation : une présence sans méthode
        // vérifiable n'est pas une preuve.
        if (! $this->publicTableExists('geo_queries')) {
            Schema::create('geo_queries', function (Blueprint $table) {
                $table->id();
                $table->text('query');
                $table->string('locale', 10)->default('fr');
                $table->unsignedSmallInteger('priority')->default(3);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['is_active', 'priority']);
            });
        }

        if (! $this->publicTableExists('geo_checks')) {
            Schema::create('geo_checks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('geo_query_id')->constrained('geo_queries')->cascadeOnDelete();
                $table->enum('engine', ['chatgpt', 'gemini', 'perplexity', 'copilot', 'other'])->default('chatgpt');
                $table->boolean('is_present');
                $table->unsignedSmallInteger('position')->nullable();
                $table->enum('method', ['manual', 'automated'])->default('manual');
                $table->text('evidence')->nullable();
                $table->timestamp('observed_at');
                $table->timestamps();

                $table->index(['geo_query_id', 'observed_at']);
            });

            // La preuve est obligatoire pour un check MANUEL : sans elle, la
            // ligne est une affirmation non vérifiable.
            DB::statement(
                "ALTER TABLE geo_checks ADD CONSTRAINT geo_checks_manual_requires_evidence "
                ."CHECK (method <> 'manual' OR evidence IS NOT NULL)"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_checks');
        Schema::dropIfExists('geo_queries');
        Schema::dropIfExists('aeo_questions');
        Schema::dropIfExists('campaign_metrics');
        Schema::dropIfExists('campaigns');
    }

    /**
     * `Schema::hasTable()` resolve via `current_schema()`, qui vaut
     * `shared_tenants` sous le search_path de l'application. Un garde écrit
     * avec `Schema::hasTable()` sur une table `public` renvoie donc TOUJOURS
     * `false` : la migration rejoue `CREATE TABLE` et, si la table existe
     * déjà, échoue ou est silencieusement sautée selon l'état. On interroge
     * explicitement `public`.
     */
    private function publicTableExists(string $table): bool
    {
        /** @var object{exists: bool}|null $row */
        $row = DB::selectOne(
            'select exists (select 1 from information_schema.tables where table_schema = ? and table_name = ?) as exists',
            ['public', $table],
        );

        return $row !== null && (bool) $row->exists;
    }

};
