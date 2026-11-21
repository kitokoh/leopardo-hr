<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Infrastructure\Services;

use App\Modules\Marketing\Domain\Models\ContentIdea;
use App\Modules\Marketing\Domain\Models\MarketingAsset;
use App\Modules\Marketing\Domain\Models\MarketingCampaign;
use App\Modules\Marketing\Domain\Models\MarketingPublication;
use App\Modules\Marketing\Domain\Models\MarketingVisibilityObjective;

/**
 * Sérialisation des entités Marketing OS vers le contrat public.
 *
 * Extrait de `PlatformMarketingOsController` (1 191 lignes) : les cinq
 * méthodes `present*` (~200 lignes) ne sont pas du HTTP, ce sont les règles
 * de ce qui est exposé. Elles portent des décisions de sécurité et de
 * véracité qui doivent être testables sans monter une requête HTTP :
 *
 *  - le `path` de stockage d'une ressource n'est JAMAIS exposé (aléatoire et
 *    interne) — seul `download_name` est proposé au téléchargement (#8675) ;
 *  - aucune URL publique n'est fabriquée pour un binaire qui vit hors webroot ;
 *  - un indicateur est soit MESURÉ, soit déclaré estimation/indisponible :
 *    `has_measured_metrics` / `has_measured_stats` permettent à l'interface de
 *    ne jamais afficher un chiffre inventé.
 */
class MarketingOsPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function presentCampaign(MarketingCampaign $campaign): array
    {
        return [
            'id' => $campaign->id,
            'name' => $campaign->name,
            'description' => $campaign->description,
            'objective' => $campaign->objective->value,
            'audience' => $campaign->audience,
            'sector' => $campaign->sector,
            'product' => $campaign->product,
            'key_message' => $campaign->key_message,
            'channel' => $campaign->channel,
            'locale' => $campaign->locale,
            'starts_at' => $campaign->starts_at?->toIso8601String(),
            'ends_at' => $campaign->ends_at?->toIso8601String(),
            'owner_id' => $campaign->owner_id,
            'budget_amount' => $campaign->budget_amount,
            'budget_currency' => $campaign->budget_currency,
            'status' => $campaign->status->value,
            'created_at' => $campaign->created_at?->toIso8601String(),
            'updated_at' => $campaign->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentIdea(ContentIdea $idea): array
    {
        return [
            'id' => $idea->id,
            'campaign_id' => $idea->campaign_id,
            'title' => $idea->title,
            'description' => $idea->description,
            'objective' => $idea->objective,
            'format' => $idea->format->value,
            'channel' => $idea->channel,
            'locale' => $idea->locale,
            'audience' => $idea->audience,
            'product' => $idea->product,
            'sector' => $idea->sector,
            'key_message' => $idea->key_message,
            'production_due_at' => $idea->production_due_at?->toIso8601String(),
            'publish_due_at' => $idea->publish_due_at?->toIso8601String(),
            'owner_id' => $idea->owner_id,
            'budget_amount' => $idea->budget_amount,
            'budget_currency' => $idea->budget_currency,
            'status' => $idea->status->value,
            'next_action' => $idea->next_action,
            'brief' => $idea->brief,
            'production_task_id' => $idea->production_task_id,
            'is_production_late' => $idea->isProductionLate(),
            'created_at' => $idea->created_at?->toIso8601String(),
            'updated_at' => $idea->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentAsset(MarketingAsset $asset): array
    {
        return [
            'id' => $asset->id,
            'campaign_id' => $asset->campaign_id,
            'content_idea_id' => $asset->content_idea_id,
            'name' => $asset->name,
            'description' => $asset->description,
            'kind' => $asset->kind->value,
            'mime_type' => $asset->mime_type,
            'extension' => $asset->extension,
            'size' => $asset->size,
            // #8675 — nom lisible propose au telechargement (nom d'origine
            // sanitise, sinon reconstruit depuis le titre). Le `path` de
            // stockage n'est JAMAIS expose : il est aleatoire et interne.
            'original_name' => $asset->original_name,
            'download_name' => $asset->hasFile() ? $asset->downloadName() : null,
            // Jamais d'URL publique fabriquée ici : le binaire vit hors webroot.
            'has_file' => $asset->hasFile(),
            'locale' => $asset->locale,
            'product' => $asset->product,
            'objective' => $asset->objective,
            'sector' => $asset->sector,
            'platforms' => $asset->platforms,
            'tags' => $asset->tags,
            'version' => $asset->version,
            'validation_status' => $asset->validation_status->value,
            'author_id' => $asset->author_id,
            'rights' => $asset->rights,
            'rights_expires_at' => $asset->rights_expires_at?->toIso8601String(),
            'created_at' => $asset->created_at?->toIso8601String(),
            'updated_at' => $asset->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentVisibilityObjective(MarketingVisibilityObjective $objective): array
    {
        return [
            'id' => $objective->id,
            'channel' => $objective->channel->value,
            'channel_label' => $objective->channel->label(),
            'keyword' => $objective->keyword,
            'search_intent' => $objective->search_intent,
            'destination_url' => $objective->destination_url,
            'ad_campaign_ref' => $objective->ad_campaign_ref,
            'locale' => $objective->locale,
            'sector' => $objective->sector,
            'campaign_id' => $objective->campaign_id,
            'content_idea_id' => $objective->content_idea_id,
            'marketing_asset_id' => $objective->marketing_asset_id,
            'marketing_publication_id' => $objective->marketing_publication_id,
            'questions_answered' => $objective->questions_answered,
            'metrics' => $objective->metrics,
            'metrics_source' => $objective->metrics_source->value,
            'metrics_source_label' => $objective->metrics_source->label(),
            'metrics_note' => $objective->metrics_note,
            // Un indicateur est soit MESURE, soit declare estimation ou
            // indisponible : jamais presente comme un fait sans provenance.
            'has_measured_metrics' => $objective->hasMeasuredMetrics(),
            'has_linked_content' => $objective->hasLinkedContent(),
            'notes' => $objective->notes,
            'created_at' => $objective->created_at?->toIso8601String(),
            'updated_at' => $objective->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentPublication(MarketingPublication $publication): array
    {
        return [
            'id' => $publication->id,
            'content_idea_id' => $publication->content_idea_id,
            'marketing_asset_id' => $publication->marketing_asset_id,
            'platform' => $publication->platform,
            'mode' => $publication->mode->value,
            'status' => $publication->status->value,
            'title' => $publication->title,
            'body' => $publication->body,
            'hashtags' => $publication->hashtags,
            'call_to_action' => $publication->call_to_action,
            'destination_url' => $publication->destination_url,
            'scheduled_at' => $publication->scheduled_at?->toIso8601String(),
            'published_at' => $publication->published_at?->toIso8601String(),
            'published_url' => $publication->published_url,
            'platform_account' => $publication->platform_account,
            'stats' => $publication->stats,
            'stats_source' => $publication->stats_source,
            'external_post_id' => $publication->external_post_id,
            'stats_fetched_at' => $publication->stats_fetched_at?->toIso8601String(),
            'stats_error' => $publication->stats_error,
            // Un indicateur est soit MESURE, soit declare indisponible :
            // l'interface ne doit jamais afficher un chiffre invente.
            'has_measured_stats' => $publication->hasMeasuredStats(),
            'error_message' => $publication->error_message,
            'attempts' => $publication->attempts,
            'attempt_history' => $publication->attempt_history,
            'is_published' => $publication->isPublished(),
            'created_at' => $publication->created_at?->toIso8601String(),
            'updated_at' => $publication->updated_at?->toIso8601String(),
        ];
    }
}
