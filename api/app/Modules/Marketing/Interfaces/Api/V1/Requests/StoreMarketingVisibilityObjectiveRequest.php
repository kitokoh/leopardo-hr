<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Interfaces\Api\V1\Requests;

use App\Modules\Marketing\Domain\Enums\MarketingVisibilityChannel;
use App\Modules\Marketing\Domain\Enums\MarketingVisibilityMetricsSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * #8531 — creation/mise a jour d'un objectif de visibilite SEO/SEA/AEO/GEO.
 *
 * Le canal est contraint a la liste fermee des quatre canaux du brief, et la
 * provenance des indicateurs a `measured | estimated | unavailable` : on ne
 * peut donc pas enregistrer un chiffre sans declarer d'ou il vient.
 */
class StoreMarketingVisibilityObjectiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'channel' => ['required', Rule::in(MarketingVisibilityChannel::values())],
            'keyword' => ['nullable', 'string', 'max:191'],
            'search_intent' => ['nullable', 'string', 'max:32'],
            'destination_url' => ['nullable', 'url', 'max:512'],
            'ad_campaign_ref' => ['nullable', 'string', 'max:191'],
            'locale' => ['nullable', 'string', 'max:8'],
            'sector' => ['nullable', 'string', 'max:64'],
            'campaign_id' => ['nullable', 'integer'],
            'content_idea_id' => ['nullable', 'integer'],
            'marketing_asset_id' => ['nullable', 'integer'],
            'marketing_publication_id' => ['nullable', 'integer'],
            'questions_answered' => ['nullable', 'array'],
            'questions_answered.*' => ['string', 'max:500'],
            'metrics' => ['nullable', 'array'],
            // La provenance est OBLIGATOIRE des qu'un indicateur est fourni :
            // un chiffre sans source declaree serait presente comme un fait.
            'metrics_source' => ['nullable', Rule::in(MarketingVisibilityMetricsSource::values())],
            'metrics_note' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
