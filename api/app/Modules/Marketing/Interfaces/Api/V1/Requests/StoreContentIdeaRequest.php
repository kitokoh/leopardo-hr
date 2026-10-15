<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Interfaces\Api\V1\Requests;

use App\Modules\Marketing\Domain\Enums\ContentIdeaStatus;
use App\Modules\Marketing\Domain\Enums\MarketingContentFormat;
use App\Modules\Marketing\Domain\Enums\MarketingObjective;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Marketing OS — création / mise à jour d'une idée ou d'un brief (brief §B).
 *
 * `brief` est un JSON libre et volontairement NON contraint champ par champ :
 * un brief vidéo (concept, script, storyboard, scènes, narration, montage,
 * prompts vidéo/image, versions linguistiques) et un brief d'article n'ont pas
 * la même forme. Contrainte minimale ici ; la fiche studio est documentée par
 * format dans le document de conception (TAD/TRD).
 */
class StoreContentIdeaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Garde portée par la route (platform.permission).
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'campaign_id' => ['nullable', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'objective' => ['nullable', 'string', Rule::in(MarketingObjective::values())],
            'format' => ['nullable', 'string', Rule::in(MarketingContentFormat::values())],
            'channel' => ['nullable', 'string', 'max:60'],
            'locale' => ['nullable', 'string', 'max:10'],
            'audience' => ['nullable', 'string', 'max:2000'],
            'product' => ['nullable', 'string', 'max:120'],
            'sector' => ['nullable', 'string', 'max:60'],
            'key_message' => ['nullable', 'string', 'max:2000'],
            'production_due_at' => ['nullable', 'date'],
            'publish_due_at' => ['nullable', 'date'],
            'owner_id' => ['nullable', 'integer', 'min:1'],
            'budget_amount' => ['nullable', 'numeric', 'min:0'],
            'budget_currency' => ['nullable', 'string', 'size:3'],
            'status' => ['nullable', 'string', Rule::in(ContentIdeaStatus::values())],
            'next_action' => ['nullable', 'string', 'max:1000'],
            'brief' => ['nullable', 'array'],
        ];
    }
}
