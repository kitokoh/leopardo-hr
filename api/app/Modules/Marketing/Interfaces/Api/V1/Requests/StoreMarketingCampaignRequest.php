<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Interfaces\Api\V1\Requests;

use App\Modules\Marketing\Domain\Enums\MarketingCampaignStatus;
use App\Modules\Marketing\Domain\Enums\MarketingObjective;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Marketing OS — création / mise à jour d'une campagne (brief §B, §I).
 *
 * Le vocabulaire de statut est contraint par l'énumération PHP, jamais par
 * une chaîne libre : la base reste indexable et l'UI peut compter par statut.
 */
class StoreMarketingCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Garde portée par la route (platform.permission).
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'objective' => ['nullable', 'string', Rule::in(MarketingObjective::values())],
            'audience' => ['nullable', 'string', 'max:2000'],
            'sector' => ['nullable', 'string', 'max:60'],
            'product' => ['nullable', 'string', 'max:120'],
            'key_message' => ['nullable', 'string', 'max:2000'],
            'channel' => ['nullable', 'string', 'max:60'],
            'locale' => ['nullable', 'string', 'max:10'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'owner_id' => ['nullable', 'integer', 'min:1'],
            'budget_amount' => ['nullable', 'numeric', 'min:0'],
            'budget_currency' => ['nullable', 'string', 'size:3'],
            'status' => ['nullable', 'string', Rule::in(MarketingCampaignStatus::values())],
        ];
    }
}
