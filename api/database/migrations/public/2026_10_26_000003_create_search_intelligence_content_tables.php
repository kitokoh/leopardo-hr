<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GSI-1 (#8512) — contenu marketing et veille concurrentielle.
 *
 * Deux règles de provenance, portées par le schéma :
 *
 *  1. **Contenu éditorial à statuts contrôlés.** `content_items.status` ne
 *     saute jamais de `draft` à `published` : la publication exige un
 *     `published_at` et un `author_id`, contrôlés par CHECK.
 *
 *  2. **Une comparaison concurrentielle sans source datée n'existe pas.**
 *     `competitors.source_url`, `source_label` et `observed_at` sont NOT NULL :
 *     on ne peut pas enregistrer un « concurrent fait X » sans dire où et quand
 *     on l'a lu. C'est la traduction en base de la règle produit « sources
 *     datées obligatoires ».
 *
 * @see docs/architecture/bounded-context-registry.json (entrée `search-intelligence`)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->publicTableExists('content_items')) {
            Schema::create('content_items', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('slug')->nullable();
                $table->enum('kind', ['article', 'landing', 'faq', 'newsletter', 'social'])->default('article');
                $table->enum('status', ['draft', 'in_review', 'approved', 'published', 'archived'])->default('draft');
                $table->longText('body')->nullable();
                $table->string('locale', 10)->default('fr');
                $table->foreignId('author_id')->nullable()->constrained('super_admins')->nullOnDelete();
                $table->foreignId('keyword_id')->nullable()->constrained('keywords')->nullOnDelete();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'kind']);
                $table->index('locale');
            });

            // Publier sans auteur ni date de publication rendrait l'état
            // « publié » indéterminable côté éditorial.
            DB::statement(
                "ALTER TABLE content_items ADD CONSTRAINT content_items_publish_requires_author_and_date "
                ."CHECK (status <> 'published' OR (published_at IS NOT NULL AND author_id IS NOT NULL))"
            );
        }

        if (! $this->publicTableExists('competitors')) {
            Schema::create('competitors', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('website_url')->nullable();
                $table->string('market', 80)->nullable();
                // Provenance OBLIGATOIRE : où l'information a été lue…
                $table->text('source_url');
                $table->string('source_label', 120);
                // …et quand. Une observation non datée est invérifiable.
                $table->timestamp('observed_at');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['market', 'observed_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('competitors');
        Schema::dropIfExists('content_items');
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
