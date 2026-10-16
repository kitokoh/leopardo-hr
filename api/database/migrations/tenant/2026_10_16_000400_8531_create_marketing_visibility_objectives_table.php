<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8531 — Objectifs de visibilite SEO / SEA / AEO / GEO (brief §H).
 *
 * Le brief exige que le Marketing OS s'INTEGRE au centre de visibilite en
 * ligne existant, sans creer de systeme parallele. L'audit
 * (`docs/specifications/MARKETING_OS_AUDIT.md`) a etabli que ce centre vit
 * cote vitrine (`robots.ts`, `JsonLd`, `ai-search.ts`, sitemap Next.js) et
 * qu'il n'existe AUCUNE donnee exploitable cote administration.
 *
 * Cette table ne duplique donc PAS la vitrine : elle stocke ce qui manque
 * reellement — l'INTENTION de visibilite (mot-cle, intention de recherche,
 * page de destination, campagne) et son rattachement a un contenu. La page
 * de destination est une REFERENCE vers la vitrine existante, pas une copie
 * de son contenu.
 *
 * `metrics_source` porte la regle non negociable du brief : un indicateur
 * est `measured`, `estimated` ou `unavailable` — jamais un chiffre presente
 * comme un fait sans que sa provenance soit explicite. Aucune visibilite
 * garantie n'est promise dans les moteurs de recherche ou les assistants IA.
 *
 * Additive et idempotente ; `down()` limite a la table creee ici.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('marketing_visibility_objectives')) {
            return;
        }

        Schema::create('marketing_visibility_objectives', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id');

            // Rattachements optionnels : un objectif peut exister avant que le
            // contenu qui le sert ne soit produit (c'est meme le cas normal).
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->unsignedBigInteger('content_idea_id')->nullable();
            $table->unsignedBigInteger('marketing_asset_id')->nullable();
            $table->unsignedBigInteger('marketing_publication_id')->nullable();

            // Canal de visibilite : SEO (moteurs), SEA (payant), AEO
            // (reponses directes), GEO (moteurs generatifs / assistants IA).
            $table->string('channel', 16);

            $table->string('keyword', 191)->nullable();
            $table->string('search_intent', 32)->nullable();
            $table->string('destination_url', 512)->nullable();
            $table->string('ad_campaign_ref', 191)->nullable();
            $table->string('locale', 8)->default('fr');
            $table->string('sector', 64)->nullable();

            // Questions auxquelles le contenu repond (AEO/GEO).
            $table->jsonb('questions_answered')->nullable();

            // Indicateurs observes + provenance explicite.
            $table->jsonb('metrics')->nullable();
            $table->string('metrics_source', 16)->default('unavailable');
            $table->text('metrics_note')->nullable();

            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestampsTz();

            $table->index(['company_id', 'channel']);
            $table->index(['company_id', 'campaign_id']);
            $table->index(['company_id', 'content_idea_id']);
            $table->index(['company_id', 'marketing_asset_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("COMMENT ON TABLE marketing_visibility_objectives IS 'Objectifs de visibilite SEO/SEA/AEO/GEO rattaches aux contenus (#8531). Ne duplique pas la vitrine : reference sa page de destination.';");
            DB::statement("COMMENT ON COLUMN marketing_visibility_objectives.metrics_source IS 'measured | estimated | unavailable — un indicateur est mesure ou explicitement identifie comme estimation (#8531).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_visibility_objectives');
    }
};
