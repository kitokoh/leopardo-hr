<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TRAVEL-902/903 (#6105/#6106) — Commentaires, likes, partages, notes.
 * Unicité (tenant, acteur, cible) pour l'anti-doublon ; modération des
 * commentaires (statuts + signalement).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('travel_comments')) {
            // Issue #7452 — la table est créée par 2026_08_30_000014_6105_create_travel_comments_table.php ; cette
            // génération ne rattrape que les colonnes qui lui manquent.
            Schema::table('travel_comments', function (Blueprint $table): void {
                if (! schemaHasColumn('travel_comments', 'company_id')) {
                    $table->uuid('company_id')->index()->nullable();
                }
                if (! schemaHasColumn('travel_comments', 'article_id')) {
                    $table->unsignedBigInteger('article_id')->nullable();
                }
                if (! schemaHasColumn('travel_comments', 'author_type')) {
                    $table->string('author_type', 20)->nullable();
                }
                if (! schemaHasColumn('travel_comments', 'author_id')) {
                    // employee|contact
                    $table->unsignedBigInteger('author_id')->nullable();
                }
                if (! schemaHasColumn('travel_comments', 'content_redacted')) {
                    $table->string('content_redacted', 2000)->nullable();
                }
                if (! schemaHasColumn('travel_comments', 'status')) {
                    $table->string('status', 20)->default('pending');
                }
                if (! schemaHasColumn('travel_comments', 'moderated_by_user_id')) {
                    // pending|approved|rejected|flagged
                    $table->unsignedBigInteger('moderated_by_user_id')->nullable();
                }
                if (! schemaHasColumn('travel_comments', 'moderated_at')) {
                    $table->timestamp('moderated_at')->nullable();
                }
                if (! schemaHasColumn('travel_comments', 'created_at')) {
                    $table->timestamps();
                }
            });
        }

        if (schemaTableExists('travel_likes')) {
            // Issue #7452 — la table est créée par 2026_08_30_000015_6106_create_travel_engagement_tables.php ; cette
            // génération ne rattrape que les colonnes qui lui manquent.
            Schema::table('travel_likes', function (Blueprint $table): void {
                if (! schemaHasColumn('travel_likes', 'company_id')) {
                    $table->uuid('company_id')->index()->nullable();
                }
                if (! schemaHasColumn('travel_likes', 'article_id')) {
                    $table->unsignedBigInteger('article_id')->nullable();
                }
                if (! schemaHasColumn('travel_likes', 'actor_type')) {
                    $table->string('actor_type', 20)->nullable();
                }
                if (! schemaHasColumn('travel_likes', 'actor_id')) {
                    // employee|contact
                    $table->unsignedBigInteger('actor_id')->nullable();
                }
                if (! schemaHasColumn('travel_likes', 'created_at')) {
                    $table->timestamps();
                }
            });
        }

        if (schemaTableExists('travel_shares')) {
            // Issue #7452 — la table est créée par 2026_08_30_000015_6106_create_travel_engagement_tables.php ; cette
            // génération ne rattrape que les colonnes qui lui manquent.
            Schema::table('travel_shares', function (Blueprint $table): void {
                if (! schemaHasColumn('travel_shares', 'company_id')) {
                    $table->uuid('company_id')->index()->nullable();
                }
                if (! schemaHasColumn('travel_shares', 'article_id')) {
                    $table->unsignedBigInteger('article_id')->nullable();
                }
                if (! schemaHasColumn('travel_shares', 'channel')) {
                    $table->string('channel', 30)->nullable();
                }
                if (! schemaHasColumn('travel_shares', 'actor_type')) {
                    $table->string('actor_type', 20)->nullable();
                }
                if (! schemaHasColumn('travel_shares', 'actor_id')) {
                    $table->unsignedBigInteger('actor_id')->nullable();
                }
                if (! schemaHasColumn('travel_shares', 'created_at')) {
                    $table->timestamps();
                }
            });
        }

        if (schemaTableExists('travel_ratings')) {
            // Issue #7452 — la table est créée par 2026_08_30_000015_6106_create_travel_engagement_tables.php ; cette
            // génération ne rattrape que les colonnes qui lui manquent.
            Schema::table('travel_ratings', function (Blueprint $table): void {
                if (! schemaHasColumn('travel_ratings', 'company_id')) {
                    $table->uuid('company_id')->index()->nullable();
                }
                if (! schemaHasColumn('travel_ratings', 'article_id')) {
                    $table->unsignedBigInteger('article_id')->nullable();
                }
                if (! schemaHasColumn('travel_ratings', 'actor_type')) {
                    $table->string('actor_type', 20)->nullable();
                }
                if (! schemaHasColumn('travel_ratings', 'actor_id')) {
                    // employee|contact
                    $table->unsignedBigInteger('actor_id')->nullable();
                }
                if (! schemaHasColumn('travel_ratings', 'rating')) {
                    $table->unsignedTinyInteger('rating')->nullable();
                }
                if (! schemaHasColumn('travel_ratings', 'created_at')) {
                    $table->timestamps();
                }
            });
        }
    }

    public function down(): void
    {
        if (schemaHasColumn('travel_ratings', 'company_id')) {
            Schema::table('travel_ratings', function (Blueprint $table): void {
                $table->dropColumn('company_id');
            });
        }
        if (schemaHasColumn('travel_ratings', 'article_id')) {
            Schema::table('travel_ratings', function (Blueprint $table): void {
                $table->dropColumn('article_id');
            });
        }
        if (schemaHasColumn('travel_ratings', 'actor_type')) {
            Schema::table('travel_ratings', function (Blueprint $table): void {
                $table->dropColumn('actor_type');
            });
        }
        if (schemaHasColumn('travel_ratings', 'actor_id')) {
            Schema::table('travel_ratings', function (Blueprint $table): void {
                $table->dropColumn('actor_id');
            });
        }
        if (schemaHasColumn('travel_ratings', 'rating')) {
            Schema::table('travel_ratings', function (Blueprint $table): void {
                $table->dropColumn('rating');
            });
        }
        if (schemaHasColumn('travel_ratings', 'created_at')) {
            Schema::table('travel_ratings', function (Blueprint $table): void {
                $table->dropColumn('created_at');
            });
        }
        if (schemaHasColumn('travel_shares', 'company_id')) {
            Schema::table('travel_shares', function (Blueprint $table): void {
                $table->dropColumn('company_id');
            });
        }
        if (schemaHasColumn('travel_shares', 'article_id')) {
            Schema::table('travel_shares', function (Blueprint $table): void {
                $table->dropColumn('article_id');
            });
        }
        if (schemaHasColumn('travel_shares', 'channel')) {
            Schema::table('travel_shares', function (Blueprint $table): void {
                $table->dropColumn('channel');
            });
        }
        if (schemaHasColumn('travel_shares', 'actor_type')) {
            Schema::table('travel_shares', function (Blueprint $table): void {
                $table->dropColumn('actor_type');
            });
        }
        if (schemaHasColumn('travel_shares', 'actor_id')) {
            Schema::table('travel_shares', function (Blueprint $table): void {
                $table->dropColumn('actor_id');
            });
        }
        if (schemaHasColumn('travel_shares', 'created_at')) {
            Schema::table('travel_shares', function (Blueprint $table): void {
                $table->dropColumn('created_at');
            });
        }
        if (schemaHasColumn('travel_likes', 'company_id')) {
            Schema::table('travel_likes', function (Blueprint $table): void {
                $table->dropColumn('company_id');
            });
        }
        if (schemaHasColumn('travel_likes', 'article_id')) {
            Schema::table('travel_likes', function (Blueprint $table): void {
                $table->dropColumn('article_id');
            });
        }
        if (schemaHasColumn('travel_likes', 'actor_type')) {
            Schema::table('travel_likes', function (Blueprint $table): void {
                $table->dropColumn('actor_type');
            });
        }
        if (schemaHasColumn('travel_likes', 'actor_id')) {
            Schema::table('travel_likes', function (Blueprint $table): void {
                $table->dropColumn('actor_id');
            });
        }
        if (schemaHasColumn('travel_likes', 'created_at')) {
            Schema::table('travel_likes', function (Blueprint $table): void {
                $table->dropColumn('created_at');
            });
        }
        if (schemaHasColumn('travel_comments', 'company_id')) {
            Schema::table('travel_comments', function (Blueprint $table): void {
                $table->dropColumn('company_id');
            });
        }
        if (schemaHasColumn('travel_comments', 'article_id')) {
            Schema::table('travel_comments', function (Blueprint $table): void {
                $table->dropColumn('article_id');
            });
        }
        if (schemaHasColumn('travel_comments', 'author_type')) {
            Schema::table('travel_comments', function (Blueprint $table): void {
                $table->dropColumn('author_type');
            });
        }
        if (schemaHasColumn('travel_comments', 'author_id')) {
            Schema::table('travel_comments', function (Blueprint $table): void {
                $table->dropColumn('author_id');
            });
        }
        if (schemaHasColumn('travel_comments', 'content_redacted')) {
            Schema::table('travel_comments', function (Blueprint $table): void {
                $table->dropColumn('content_redacted');
            });
        }
        if (schemaHasColumn('travel_comments', 'status')) {
            Schema::table('travel_comments', function (Blueprint $table): void {
                $table->dropColumn('status');
            });
        }
        if (schemaHasColumn('travel_comments', 'moderated_by_user_id')) {
            Schema::table('travel_comments', function (Blueprint $table): void {
                $table->dropColumn('moderated_by_user_id');
            });
        }
        if (schemaHasColumn('travel_comments', 'moderated_at')) {
            Schema::table('travel_comments', function (Blueprint $table): void {
                $table->dropColumn('moderated_at');
            });
        }
        if (schemaHasColumn('travel_comments', 'created_at')) {
            Schema::table('travel_comments', function (Blueprint $table): void {
                $table->dropColumn('created_at');
            });
        }
    }
};
