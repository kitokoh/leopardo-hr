<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Leopardo Library — bibliothèque numérique : catégories, ressources, auteurs,
 * collections, signalements.
 *
 * - `library_categories` : catégories de contenu (administrables, extensibles),
 *   slug unique par tenant (ou global si `company_id` NULL pour le catalogue
 *   public Leopardo) ;
 * - `library_authors`    : auteurs / éditeurs ;
 * - `library_collections`: collections éditoriales (regroupements) ;
 * - `library_resources`  : livres / documents (métadonnées, modèle d'accès,
 *   licence, droits, statut éditorial, fichier stocké côté objet) ;
 * - `library_reports`    : signalements de contenu (procédure de retrait).
 *
 * Tenant-scoped (`company_id` nullable : NULL = catalogue public Leopardo,
 * non-NULL = contenu professionnel privé d'un tenant). Isolation stricte :
 * une ressource `private` n'est jamais servie hors de son tenant.
 *
 * Prix en minor units (entier) + devise ISO 4217 — jamais de flottants.
 * Idempotente + down() complet (conventions migrations tenant §2.6).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('library_categories')) {
            Schema::create('library_categories', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->nullable()->index();

                $table->string('name', 120);
                $table->string('slug', 130);
                $table->string('icon', 40)->nullable();
                $table->smallInteger('position')->default(0);

                $table->timestamps();

                $table->unique(['company_id', 'slug'], 'library_categories_company_slug_unique');
            });

            DB::statement("COMMENT ON TABLE library_categories IS 'Categories de la bibliotheque - slug unique par tenant (NULL = catalogue public Leopardo).';");
        }

        if (! schemaTableExists('library_authors')) {
            Schema::create('library_authors', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->nullable()->index();

                $table->string('name', 150);
                $table->string('slug', 160);
                $table->text('bio')->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'slug'], 'library_authors_company_slug_unique');
            });

            DB::statement("COMMENT ON TABLE library_authors IS 'Auteurs et editeurs de la bibliotheque.';");
        }

        if (! schemaTableExists('library_collections')) {
            Schema::create('library_collections', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->nullable()->index();

                $table->string('name', 150);
                $table->string('slug', 160);
                $table->text('description')->nullable();
                $table->smallInteger('position')->default(0);

                $table->timestamps();

                $table->unique(['company_id', 'slug'], 'library_collections_company_slug_unique');
            });

            DB::statement("COMMENT ON TABLE library_collections IS 'Collections editoriales de la bibliotheque.';");
        }

        if (! schemaTableExists('library_resources')) {
            Schema::create('library_resources', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->nullable()->index();

                $table->unsignedBigInteger('category_id')->nullable();
                $table->unsignedBigInteger('author_id')->nullable();
                $table->unsignedBigInteger('collection_id')->nullable();

                $table->string('title', 200);
                $table->string('slug', 210);
                $table->text('description')->nullable();
                $table->text('excerpt')->nullable();

                // Type de document + langue principale (ISO 639-1).
                $table->string('document_type', 20)->default('book');
                $table->char('language', 2)->default('fr');

                // Modèle d'accès + statut éditorial (workflow).
                $table->string('access_model', 20)->default('free');
                $table->string('status', 20)->default('draft');

                // Prix (minor units) + devise ISO 4217 — jamais de flottant.
                $table->unsignedBigInteger('price_minor')->default(0);
                $table->char('currency', 3)->default('EUR');

                // Métadonnées bibliographiques.
                $table->string('publisher', 150)->nullable();
                $table->smallInteger('published_year')->nullable();
                $table->unsignedInteger('pages')->nullable();

                // Droits d'auteur et protection des contenus.
                $table->string('license_id', 60)->default('proprietary');
                $table->string('rights_holder', 200)->nullable();
                $table->boolean('allow_download')->default(false);
                $table->boolean('allow_redistribution')->default(false);
                $table->json('geo_restrictions')->nullable();

                // Fichier stocké côté objet (jamais d'URL publique permanente).
                $table->string('file_key', 300)->nullable();
                $table->string('file_format', 20)->nullable();
                $table->unsignedBigInteger('file_size')->nullable();

                // Mise en avant éditoriale + nouveauté.
                $table->boolean('featured')->default(false);
                $table->boolean('is_new')->default(false);

                // Traçabilité éditoriale (employees.id = entier).
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('published_by')->nullable();
                $table->timestamp('published_at')->nullable();

                $table->json('meta')->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'slug'], 'library_resources_company_slug_unique');
                $table->index(['company_id', 'status'], 'library_resources_company_status_idx');
                $table->index(['company_id', 'category_id'], 'library_resources_company_category_idx');
                $table->index(['company_id', 'access_model'], 'library_resources_company_access_idx');
            });

            DB::statement("COMMENT ON TABLE library_resources IS 'Ressources de la bibliotheque - metadonnees, modele d acces, licence, statut editorial (workflow draft|pending|published|archived).';");
        }

        if (! schemaTableExists('library_reports')) {
            Schema::create('library_reports', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->nullable()->index();

                $table->unsignedBigInteger('resource_id');
                $table->string('reason', 40);
                $table->text('details')->nullable();
                $table->string('reporter_email', 200)->nullable();
                $table->string('status', 20)->default('open');

                $table->timestamps();

                $table->index(['resource_id', 'status'], 'library_reports_resource_status_idx');
            });

            DB::statement("COMMENT ON TABLE library_reports IS 'Signalements de contenu (procedure de retrait, droits d auteur).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('library_reports');
        Schema::dropIfExists('library_resources');
        Schema::dropIfExists('library_collections');
        Schema::dropIfExists('library_authors');
        Schema::dropIfExists('library_categories');
    }
};
