<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Interfaces\Api\V1\Requests;

use App\Modules\Marketing\Domain\Enums\MarketingPublicationMode;
use App\Modules\Marketing\Domain\Enums\MarketingPublicationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Marketing OS — préparation d'une publication sur UNE plateforme (brief §E).
 *
 * `mode` n'est qu'une DEMANDE du client : le contrôleur le confronte à la
 * disponibilité réelle d'un connecteur (`social_accounts` actif) et retombe
 * sur `assisted` si l'automatisation n'est pas réellement possible. Un client
 * ne peut donc pas faire afficher « publication automatique » à tort.
 */
class StoreMarketingPublicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Garde portée par la route (platform.permission).
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'content_idea_id' => ['nullable', 'integer', 'min:1'],
            'marketing_asset_id' => ['nullable', 'integer', 'min:1'],
            'platform' => ['required', 'string', 'max:40'],
            'mode' => ['nullable', 'string', Rule::in(MarketingPublicationMode::values())],
            'status' => ['nullable', 'string', Rule::in(MarketingPublicationStatus::values())],
            'title' => ['nullable', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:20000'],
            'hashtags' => ['nullable', 'string', 'max:2000'],
            'call_to_action' => ['nullable', 'string', 'max:500'],
            'destination_url' => ['nullable', 'url', 'max:512'],
            'scheduled_at' => ['nullable', 'date'],
        ];
    }
}
