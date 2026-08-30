<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TRAVEL-901 (#6104) — Articles & catégories (contenu éditorial legacy).
 * Statuts brouillon/publié/signalé + modération (moderated_by, moderated_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_article_categories')) {
            Schema::create('travel_article_categories', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('slug', 80);
                $table->string('name', 150);
                $table->timestamps();
                $table->unique(['company_id', 'slug'], 'travel_article_categories_company_slug_unique');
            });

            DB::statement("COMMENT ON TABLE travel_article_categories IS 'Catégories d''articles éditoriaux (TRAVEL-901/#6104).'");
        }

        if (! schemaTableExists('travel_articles')) {
            Schema::create('travel_articles', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('category_id')->nullable();
                $table->string('slug', 100);
                $table->string('title', 200);
                $table->text('body_redacted');
                $table->string('status', 20)->default('draft'); // draft|published|flagged
                $table->string('author_type', 20)->nullable(); // employee|contact
                $table->unsignedBigInteger('author_id')->nullable();
                $table->unsignedBigInteger('moderated_by_user_id')->nullable();
                $table->timestamp('moderated_at')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'slug'], 'travel_articles_company_slug_unique');
                $table->index(['company_id', 'status'], 'travel_articles_company_status_idx');
            });

            DB::statement("COMMENT ON TABLE travel_articles IS 'Articles éditoriaux - statuts draft/published/flagged (TRAVEL-901/#6104).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_articles');
        Schema::dropIfExists('travel_article_categories');
    }
};
