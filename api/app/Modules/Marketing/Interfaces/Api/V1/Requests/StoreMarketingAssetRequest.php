<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Interfaces\Api\V1\Requests;

use App\Modules\Marketing\Domain\Enums\MarketingAssetKind;
use App\Modules\Marketing\Domain\Enums\MarketingAssetValidationStatus;
use App\Modules\Marketing\Domain\Enums\MarketingObjective;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Marketing OS — ajout d'une ressource à la médiathèque (brief §C).
 *
 * Le fichier est optionnel : un script, un prompt ou une légende est un
 * contenu TEXTE (`body`), pas un binaire. L'inverse n'est pas vrai : les
 * types qui exigent un fichier (vidéo, image, logo, audio, document) sont
 * contrôlés après validation, dans le contrôleur, une fois le type connu.
 */
class StoreMarketingAssetRequest extends FormRequest
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
            'content_idea_id' => ['nullable', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'kind' => ['required', 'string', Rule::in(MarketingAssetKind::values())],
            'locale' => ['nullable', 'string', 'max:10'],
            'product' => ['nullable', 'string', 'max:120'],
            'objective' => ['nullable', 'string', Rule::in(MarketingObjective::values())],
            'sector' => ['nullable', 'string', 'max:60'],
            'platforms' => ['nullable', 'array', 'max:20'],
            'platforms.*' => ['string', 'max:40'],
            'tags' => ['nullable', 'array', 'max:40'],
            'tags.*' => ['string', 'max:60'],
            'validation_status' => ['nullable', 'string', Rule::in(MarketingAssetValidationStatus::values())],
            'rights' => ['nullable', 'string', 'max:2000'],
            'rights_expires_at' => ['nullable', 'date'],
            // Contenu texte (script, prompt, légende) : pas de fichier exigé.
            'body' => ['nullable', 'string', 'max:200000'],
            'file' => ['nullable', 'file'],
        ];
    }
}
