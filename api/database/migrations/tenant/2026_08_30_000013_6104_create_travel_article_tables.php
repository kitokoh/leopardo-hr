<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #6104 (TRAVEL-901) — Articles & catégories éditoriaux.
 *
 * - `travel_article_categories` : catégories uniques par tenant (code).
 * - `travel_articles` : contenu éditorial, statuts
 *   draft|published|reported|archived, publication contrôlée
 *   (`published_at`), modération (signalement tracé).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_article_categories')) {
            Schema::create('travel_article_categories', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('code', 60);
                $table->string('name', 160);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'travel_article_categories_company_code_unique');
            });
        }

        if (! schemaTableExists('travel_articles')) {
            Schema::create('travel_articles', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('category_id')->nullable();
                $table->string('title', 200);
                $table->text('body_redacted');
                $table->string('status', 20)->default('draft');
                $table->unsignedBigInteger('author_user_id')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->string('moderation_note', 500)->nullable();
                $table->timestamps();

                $table->index(['company_id', 'status'], 'travel_articles_company_status_idx');
                $table->index(['company_id', 'category_id'], 'travel_articles_company_category_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_articles');
        Schema::dropIfExists('travel_article_categories');
    }
};
