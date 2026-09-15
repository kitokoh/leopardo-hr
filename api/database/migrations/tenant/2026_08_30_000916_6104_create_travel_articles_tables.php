<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TRAVEL-901 (#6104) — Articles & catégories (contenu éditorial legacy).
 * Statuts brouillon/publié/signalé + modération (moderated_by, moderated_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('travel_article_categories')) {
            // Issue #7452 — la table est créée par 2026_08_30_000013_6104_create_travel_article_tables.php ; cette
            // génération ne rattrape que les colonnes qui lui manquent.
            Schema::table('travel_article_categories', function (Blueprint $table): void {
                if (! schemaHasColumn('travel_article_categories', 'company_id')) {
                    $table->uuid('company_id')->index()->nullable();
                }
                if (! schemaHasColumn('travel_article_categories', 'slug')) {
                    $table->string('slug', 80)->nullable();
                }
                if (! schemaHasColumn('travel_article_categories', 'name')) {
                    $table->string('name', 150)->nullable();
                }
                if (! schemaHasColumn('travel_article_categories', 'created_at')) {
                    $table->timestamps();
                }
            });
        }

        if (schemaTableExists('travel_articles')) {
            // Issue #7452 — la table est créée par 2026_08_30_000013_6104_create_travel_article_tables.php ; cette
            // génération ne rattrape que les colonnes qui lui manquent.
            Schema::table('travel_articles', function (Blueprint $table): void {
                if (! schemaHasColumn('travel_articles', 'company_id')) {
                    $table->uuid('company_id')->index()->nullable();
                }
                if (! schemaHasColumn('travel_articles', 'category_id')) {
                    $table->unsignedBigInteger('category_id')->nullable();
                }
                if (! schemaHasColumn('travel_articles', 'slug')) {
                    $table->string('slug', 100)->nullable();
                }
                if (! schemaHasColumn('travel_articles', 'title')) {
                    $table->string('title', 200)->nullable();
                }
                if (! schemaHasColumn('travel_articles', 'body_redacted')) {
                    $table->text('body_redacted')->nullable();
                }
                if (! schemaHasColumn('travel_articles', 'status')) {
                    $table->string('status', 20)->default('draft');
                }
                if (! schemaHasColumn('travel_articles', 'author_type')) {
                    // draft|published|flagged
                    $table->string('author_type', 20)->nullable();
                }
                if (! schemaHasColumn('travel_articles', 'author_id')) {
                    // employee|contact
                    $table->unsignedBigInteger('author_id')->nullable();
                }
                if (! schemaHasColumn('travel_articles', 'moderated_by_user_id')) {
                    $table->unsignedBigInteger('moderated_by_user_id')->nullable();
                }
                if (! schemaHasColumn('travel_articles', 'moderated_at')) {
                    $table->timestamp('moderated_at')->nullable();
                }
                if (! schemaHasColumn('travel_articles', 'published_at')) {
                    $table->timestamp('published_at')->nullable();
                }
                if (! schemaHasColumn('travel_articles', 'created_at')) {
                    $table->timestamps();
                }
            });
        }
    }

    public function down(): void
    {
        if (schemaHasColumn('travel_articles', 'company_id')) {
            Schema::table('travel_articles', function (Blueprint $table): void {
                $table->dropColumn('company_id');
            });
        }
        if (schemaHasColumn('travel_articles', 'category_id')) {
            Schema::table('travel_articles', function (Blueprint $table): void {
                $table->dropColumn('category_id');
            });
        }
        if (schemaHasColumn('travel_articles', 'slug')) {
            Schema::table('travel_articles', function (Blueprint $table): void {
                $table->dropColumn('slug');
            });
        }
        if (schemaHasColumn('travel_articles', 'title')) {
            Schema::table('travel_articles', function (Blueprint $table): void {
                $table->dropColumn('title');
            });
        }
        if (schemaHasColumn('travel_articles', 'body_redacted')) {
            Schema::table('travel_articles', function (Blueprint $table): void {
                $table->dropColumn('body_redacted');
            });
        }
        if (schemaHasColumn('travel_articles', 'status')) {
            Schema::table('travel_articles', function (Blueprint $table): void {
                $table->dropColumn('status');
            });
        }
        if (schemaHasColumn('travel_articles', 'author_type')) {
            Schema::table('travel_articles', function (Blueprint $table): void {
                $table->dropColumn('author_type');
            });
        }
        if (schemaHasColumn('travel_articles', 'author_id')) {
            Schema::table('travel_articles', function (Blueprint $table): void {
                $table->dropColumn('author_id');
            });
        }
        if (schemaHasColumn('travel_articles', 'moderated_by_user_id')) {
            Schema::table('travel_articles', function (Blueprint $table): void {
                $table->dropColumn('moderated_by_user_id');
            });
        }
        if (schemaHasColumn('travel_articles', 'moderated_at')) {
            Schema::table('travel_articles', function (Blueprint $table): void {
                $table->dropColumn('moderated_at');
            });
        }
        if (schemaHasColumn('travel_articles', 'published_at')) {
            Schema::table('travel_articles', function (Blueprint $table): void {
                $table->dropColumn('published_at');
            });
        }
        if (schemaHasColumn('travel_articles', 'created_at')) {
            Schema::table('travel_articles', function (Blueprint $table): void {
                $table->dropColumn('created_at');
            });
        }
        if (schemaHasColumn('travel_article_categories', 'company_id')) {
            Schema::table('travel_article_categories', function (Blueprint $table): void {
                $table->dropColumn('company_id');
            });
        }
        if (schemaHasColumn('travel_article_categories', 'slug')) {
            Schema::table('travel_article_categories', function (Blueprint $table): void {
                $table->dropColumn('slug');
            });
        }
        if (schemaHasColumn('travel_article_categories', 'name')) {
            Schema::table('travel_article_categories', function (Blueprint $table): void {
                $table->dropColumn('name');
            });
        }
        if (schemaHasColumn('travel_article_categories', 'created_at')) {
            Schema::table('travel_article_categories', function (Blueprint $table): void {
                $table->dropColumn('created_at');
            });
        }
    }
};
