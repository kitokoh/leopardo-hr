<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #6105 (TRAVEL-902) — Commentaires (modération + signalement tracé).
 *
 * Contenu borné (3..1000), statut pending|approved|rejected|reported,
 * signalement : motif + horodatage, un signalement ne peut pas être émis
 * deux fois par le même auteur sur le même commentaire.
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
                $table->string('author_type', 20)->default('employee'); // employee|contact
                $table->unsignedBigInteger('author_user_id')->nullable();
                $table->string('author_name', 160)->nullable();
                $table->string('body', 1000);
                $table->string('status', 20)->default('pending');
                $table->timestamp('moderated_at')->nullable();
                $table->unsignedBigInteger('moderated_by_user_id')->nullable();
                $table->string('report_reason', 255)->nullable();
                $table->timestamp('reported_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'article_id'], 'travel_comments_company_article_idx');
                $table->index(['company_id', 'status'], 'travel_comments_company_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_comments');
    }
};
