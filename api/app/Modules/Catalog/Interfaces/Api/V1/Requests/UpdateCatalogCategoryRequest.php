<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Catalog\Domain\Models\CatalogCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'une catégorie du catalogue B2B (BC-28 CATALOG, #6881).
 *
 * Slug unique par tenant, hors catégorie courante.
 */
class UpdateCatalogCategoryRequest extends FormRequest
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

        $category = $this->route('category');
        $categoryId = $category instanceof CatalogCategory ? (int) $category->getKey() : (int) $category;

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'nullable',
                'alpha_dash',
                'max:130',
                Rule::unique('catalog_categories', 'slug')
                    ->where('company_id', (string) $actor->company_id)
                    ->ignore($categoryId),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                'not_in:'.$categoryId,
                Rule::exists('catalog_categories', 'id')
                    ->where('company_id', (string) $actor->company_id),
            ],
            'position' => ['nullable', 'integer', 'min:0', 'max:32767'],
        ];
    }
}
