<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module Marketing — Phase 1.
 *
 * social_posts : contenu a publier/planifier sur une ou plusieurs
 * plateformes via le social_account du tenant. Consomme par le job
 * planifie PublishScheduledSocialPost (Phase 4).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('social_posts')) {
            return;
        }

        Schema::create('social_posts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();
            $table->unsignedBigInteger('social_account_id')->index();

            $table->unsignedInteger('created_by')->nullable()->index();

            $table->text('content');
            // Chemins/URLs des medias (S3-compatible), pas les binaires.
            $table->json('media_paths')->nullable();

            // Plateformes cibles pour cette publication precise
            // (ex: ["linkedin", "facebook_page"]).
            $table->json('target_platforms');

            $table->string('status', 20)->default('draft')->index();
            // draft | scheduled | publishing | published | failed

            $table->timestampTz('scheduled_at')->nullable()->index();
            $table->timestampTz('published_at')->nullable();

            // Reference agregateur (ex: Ayrshare post id) pour retrouver le
            // statut ou traiter les webhooks de confirmation plus tard.
            $table->string('provider_post_ref', 120)->nullable();

            $table->text('error_message')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);

            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->index(['company_id', 'status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_posts');
    }
};
