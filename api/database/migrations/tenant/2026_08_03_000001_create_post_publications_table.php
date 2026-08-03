<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module Marketing — Issue #1432.
 *
 * post_publications : etat de publication par reseau social pour un
 * social_post donne (`docs/specifications/MODULE_MARKETING.md` §3.1).
 * Un `social_post` cible potentiellement plusieurs plateformes
 * (`target_platforms`) ; Ayrshare renvoie un statut/`postId` distinct par
 * reseau dans son tableau `postIds` — cette table persiste ce detail au
 * lieu de l'agreger dans les colonnes globales de `social_posts`
 * (`status`, `provider_post_ref`), qui ne retiennent que le resultat
 * global. Consommee par SocialPublishingService::publishNow().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('post_publications')) {
            return;
        }

        Schema::create('post_publications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();
            $table->unsignedBigInteger('social_post_id')->index();
            $table->unsignedBigInteger('social_account_id')->index();

            // Une des plateformes ciblees par le social_post parent
            // (ex: "linkedin", "facebook", "twitter").
            $table->string('platform', 40);

            $table->string('status', 20)->default('pending')->index();
            // pending | success | failed

            // Reference agregateur pour ce reseau precis (Ayrshare
            // postIds[].id), distincte du provider_post_ref global du post.
            $table->string('external_post_id', 120)->nullable();

            $table->text('error_message')->nullable();
            $table->timestampTz('published_at')->nullable();

            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->foreign('social_post_id')->references('id')->on('social_posts')->cascadeOnDelete();
            $table->foreign('social_account_id')->references('id')->on('social_accounts')->cascadeOnDelete();

            $table->unique(['social_post_id', 'platform']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_publications');
    }
};
