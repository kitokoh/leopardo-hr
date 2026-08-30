<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        if (! schemaTableExists('travel_comments')) {
            Schema::create('travel_comments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('article_id');
                $table->string('author_type', 20)->nullable(); // employee|contact
                $table->unsignedBigInteger('author_id')->nullable();
                $table->string('content_redacted', 2000);
                $table->string('status', 20)->default('pending'); // pending|approved|rejected|flagged
                $table->unsignedBigInteger('moderated_by_user_id')->nullable();
                $table->timestamp('moderated_at')->nullable();
                $table->timestamps();
                $table->index(['company_id', 'article_id'], 'travel_comments_company_article_idx');
            });

<<<<<<< HEAD
            DB::statement("COMMENT ON TABLE travel_comments IS 'Commentaires d'articles - modération (TRAVEL-902/#6105).'");
=======
            DB::statement("COMMENT ON TABLE travel_comments IS 'Commentaires d''articles - modération (TRAVEL-902/#6105).'");
>>>>>>> origin/feat/travel-101-202-foundations
        }

        if (! schemaTableExists('travel_likes')) {
            Schema::create('travel_likes', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('article_id');
                $table->string('actor_type', 20); // employee|contact
                $table->unsignedBigInteger('actor_id');
                $table->timestamps();
                $table->unique(['company_id', 'article_id', 'actor_type', 'actor_id'], 'travel_likes_company_article_actor_unique');
            });

<<<<<<< HEAD
            DB::statement("COMMENT ON TABLE travel_likes IS 'Likes d'articles - unicite (tenant, article, acteur) (TRAVEL-903/#6106).'");
=======
            DB::statement("COMMENT ON TABLE travel_likes IS 'Likes d''articles - unicite (tenant, article, acteur) (TRAVEL-903/#6106).'");
>>>>>>> origin/feat/travel-101-202-foundations
        }

        if (! schemaTableExists('travel_shares')) {
            Schema::create('travel_shares', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('article_id');
                $table->string('channel', 30);
                $table->string('actor_type', 20)->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->timestamps();
                $table->index(['company_id', 'article_id'], 'travel_shares_company_article_idx');
            });

<<<<<<< HEAD
            DB::statement("COMMENT ON TABLE travel_shares IS 'Partages d'articles (canal) (TRAVEL-903/#6106).'");
=======
            DB::statement("COMMENT ON TABLE travel_shares IS 'Partages d''articles (canal) (TRAVEL-903/#6106).'");
>>>>>>> origin/feat/travel-101-202-foundations
        }

        if (! schemaTableExists('travel_ratings')) {
            Schema::create('travel_ratings', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('article_id');
                $table->string('actor_type', 20); // employee|contact
                $table->unsignedBigInteger('actor_id');
                $table->unsignedTinyInteger('rating'); // 1..5
                $table->timestamps();
                $table->unique(['company_id', 'article_id', 'actor_type', 'actor_id'], 'travel_ratings_company_article_actor_unique');
            });

<<<<<<< HEAD
            DB::statement("COMMENT ON TABLE travel_ratings IS 'Notes d'articles 1..5 - unicite (tenant, article, acteur) (TRAVEL-903/#6106).'");
=======
            DB::statement("COMMENT ON TABLE travel_ratings IS 'Notes d''articles 1..5 - unicite (tenant, article, acteur) (TRAVEL-903/#6106).'");
>>>>>>> origin/feat/travel-101-202-foundations
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_ratings');
        Schema::dropIfExists('travel_shares');
        Schema::dropIfExists('travel_likes');
        Schema::dropIfExists('travel_comments');
    }
};
