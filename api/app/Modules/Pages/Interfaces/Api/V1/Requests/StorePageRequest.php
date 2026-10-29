<?php

declare(strict_types=1);

namespace App\Modules\Pages\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Pages\Domain\Models\Page;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création de la Page d'un établissement (BC PAGES, #8728).
 *
 * ## `site_id` est validé contre le tenant de l'ACTEUR, pas contre la base
 *
 * `Rule::exists('sites', 'id')` seul accepterait l'établissement d'un autre
 * tenant : la règle ne connaît pas le contexte. Le `where` sur `company_id`
 * ferme ce trou au niveau de la validation, et `Page::assertSameTenantAsSite()`
 * le referme au niveau du domaine — deux barrières, parce que la première
 * protège la requête et la seconde tout appelant qui construirait la Page
 * autrement.
 *
 * ## `slug` est optionnel
 *
 * Sans slug, il est dérivé du titre SEO puis du nom de l'établissement. Le
 * client n'a donc pas à inventer une URL pour créer une Page.
 */
final class StorePageRequest extends FormRequest
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
        /** @var Employee $actor */
        $actor = $this->user();

        return [
            'site_id' => [
                'required',
                'integer',
                Rule::exists('sites', 'id')->where('company_id', (string) $actor->company_id),
            ],
            'slug' => ['nullable', 'string', 'max:160'],
            'title' => ['nullable', 'string', 'max:200'],
            'theme' => ['nullable', 'string', 'max:60'],
            'settings' => ['nullable', 'array'],
            'legal' => ['nullable', 'array'],
            'seo' => ['nullable', 'array'],
            'seo.title' => ['nullable', 'string', 'max:200'],
            'seo.description' => ['nullable', 'string', 'max:500'],
            'locales' => ['nullable', 'array', 'max:8'],
            'locales.*' => ['string', Rule::in(Page::SUPPORTED_LOCALES)],
            'default_locale' => ['nullable', 'string', Rule::in(Page::SUPPORTED_LOCALES)],
        ];
    }
}
