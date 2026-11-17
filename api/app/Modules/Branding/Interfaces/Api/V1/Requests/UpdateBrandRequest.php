<?php

declare(strict_types=1);

namespace App\Modules\Branding\Interfaces\Api\V1\Requests;

use App\Modules\Branding\Domain\Enums\BrandVertical;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation de la mise à jour d'une Brand (BC-41, #9578).
 *
 * ## `slug` est absent, volontairement
 *
 * Le slug est une URL publique : le changer casse les liens entrants et le
 * référencement. Un tenant qui veut un nouveau slug crée une nouvelle Brand et
 * redirige — c'est une décision consciente, pas un effet de bord d'un
 * formulaire d'édition. Le refuser ici rend la règle visible dans le contrat
 * d'API, pas seulement dans le code.
 *
 * Tous les autres champs sont `sometimes` : une mise à jour partielle ne doit
 * pas écraser ce qu'elle ne mentionne pas.
 */
final class UpdateBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'vertical' => ['sometimes', 'string', Rule::in(BrandVertical::values())],

            'palette' => ['sometimes', 'array'],
            'palette.primary' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'palette.accent' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'palette.surface' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],

            'identity' => ['sometimes', 'array'],
            'identity.display_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'identity.tagline' => ['sometimes', 'nullable', 'string', 'max:200'],
            'identity.logo_url' => ['sometimes', 'nullable', 'string', 'max:500', 'url'],
            'identity.favicon_url' => ['sometimes', 'nullable', 'string', 'max:500', 'url'],
            'identity.og_image_url' => ['sometimes', 'nullable', 'string', 'max:500', 'url'],

            'surface' => ['sometimes', 'array'],
            'surface.path_prefix' => ['sometimes', 'nullable', 'string', 'max:60', 'regex:/^\/[a-z0-9\-\/]*$/'],
            'surface.locales' => ['sometimes', 'array'],
            'surface.locales.*' => ['string', Rule::in(['fr', 'en', 'ar', 'tr'])],
            'surface.default_locale' => ['sometimes', 'string', Rule::in(['fr', 'en', 'ar', 'tr'])],

            'host' => ['sometimes', 'nullable', 'string', 'max:190'],
        ];
    }
}
