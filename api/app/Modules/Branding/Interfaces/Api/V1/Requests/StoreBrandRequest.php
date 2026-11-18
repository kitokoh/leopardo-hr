<?php

declare(strict_types=1);

namespace App\Modules\Branding\Interfaces\Api\V1\Requests;

use App\Modules\Branding\Domain\Enums\BrandVertical;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation de la création d'une Brand (BC-41, #9578).
 *
 * ## Ce que la requête valide, et ce qu'elle laisse au domaine
 *
 * Elle valide la FORME : types, longueurs, format hexadécimal, appartenance
 * de la verticale au registre. Elle ne valide pas les RÈGLES : normalisation
 * du slug, slug réservé, calcul du contraste, unicité par tenant. Ces règles
 * vivent dans le domaine, où elles sont testables sans HTTP — et où elles
 * s'appliquent aussi à un import ou à une commande console.
 *
 * ## `on_primary` n'est pas accepté
 *
 * Le champ est délibérément absent de la validation : il est CALCULÉ. Accepter
 * une valeur cliente permettrait d'imposer un contraste insuffisant, ce que
 * tout le value object `BrandPalette` existe pour empêcher.
 */
final class StoreBrandRequest extends FormRequest
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
            'slug' => ['required', 'string', 'max:160'],
            'name' => ['required', 'string', 'max:120'],
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
