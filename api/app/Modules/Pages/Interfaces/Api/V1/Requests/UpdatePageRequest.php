<?php

declare(strict_types=1);

namespace App\Modules\Pages\Interfaces\Api\V1\Requests;

use App\Modules\Pages\Domain\Models\Page;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour du contenu éditable d'une Page (BC PAGES, #8728).
 *
 * ## Requête PARTIELLE, et pas un héritage de `StorePageRequest`
 *
 * C'est exactement le défaut corrigé sur Travel Quiz (#8975) : une requête de
 * mise à jour qui hérite de la requête de création garde `required` sur les
 * champs de création, donc tout PUT partiel répond 422. Ici chaque champ est
 * `sometimes` : envoyer `{"theme": "dark"}` ne doit pas exiger `site_id`.
 *
 * ## Les champs d'état sont ABSENTS, volontairement
 *
 * `status`, `verification_status`, `suspended_at`, `site_id` ne figurent pas
 * dans les règles. Un PUT qui les accepterait permettrait à un tenant de se
 * déclarer vérifié lui-même — ce qui annulerait la vérification (#8725/#8729).
 * Les transitions passent par leurs routes dédiées.
 */
final class UpdatePageRequest extends FormRequest
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
            'slug' => ['sometimes', 'string', 'max:160'],
            'theme' => ['sometimes', 'string', 'max:60'],
            'settings' => ['sometimes', 'nullable', 'array'],
            'legal' => ['sometimes', 'nullable', 'array'],
            'seo' => ['sometimes', 'nullable', 'array'],
            'seo.title' => ['sometimes', 'nullable', 'string', 'max:200'],
            'seo.description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'locales' => ['sometimes', 'nullable', 'array', 'max:8'],
            'locales.*' => ['string', Rule::in(Page::SUPPORTED_LOCALES)],
            'default_locale' => ['sometimes', 'string', Rule::in(Page::SUPPORTED_LOCALES)],
        ];
    }
}
