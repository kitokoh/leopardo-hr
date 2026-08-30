<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #6106 (TRAVEL-903) — Likes / partages / notes.
 *
 * Unicité (tenant, acteur, cible) → un acteur = un like par cible
 * (critère d'acceptation) ; les agrégats sont dérivés par COUNT (jamais
 * stockés) — anti-spam : un acteur ne peut noter une cible qu'une fois.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_likes')) {
            Schema::create('travel_likes', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('article_id');
                $table->string('actor_type', 20)->default('employee');
                $table->unsignedBigInteger('actor_user_id')->nullable();
                $table->string('actor_identifier', 255)->nullable();
                $table->timestamps();

                $table->unique(
                    ['company_id', 'article_id', 'actor_user_id', 'actor_identifier'],
                    'travel_likes_company_article_actor_unique',
                );
            });
        }

        if (! schemaTableExists('travel_shares')) {
            Schema::create('travel_shares', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('article_id');
                $table->string('actor_type', 20)->default('employee');
                $table->unsignedBigInteger('actor_user_id')->nullable();
                $table->string('actor_identifier', 255)->nullable();
                $table->string('channel', 40)->nullable();
                $table->timestamps();

                $table->index(['company_id', 'article_id'], 'travel_shares_company_article_idx');
            });
        }

        if (! schemaTableExists('travel_ratings')) {
            Schema::create('travel_ratings', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('article_id');
                $table->string('actor_type', 20)->default('employee');
                $table->unsignedBigInteger('actor_user_id')->nullable();
                $table->string('actor_identifier', 255)->nullable();
                $table->unsignedTinyInteger('stars');
                $table->timestamps();

                $table->unique(
                    ['company_id', 'article_id', 'actor_user_id', 'actor_identifier'],
                    'travel_ratings_company_article_actor_unique',
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_ratings');
        Schema::dropIfExists('travel_shares');
        Schema::dropIfExists('travel_likes');
    }
};
