<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Retail\Domain\Models\RetailCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'une catégorie du module Retail (BC-17 RETAIL, #7672).
 *
 * Slug unique par tenant, hors catégorie courante.
 */
class UpdateRetailCategoryRequest extends FormRequest
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
        $categoryId = $category instanceof RetailCategory ? (int) $category->getKey() : (int) $category;

        return [
            'name' => ['required', 'string', 'max:160'],
            'slug' => [
                'nullable',
                'alpha_dash',
                'max:180',
                Rule::unique('retail_categories', 'slug')
                    ->where('company_id', (string) $actor->company_id)
                    ->ignore($categoryId),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                'not_in:'.$categoryId,
                Rule::exists('retail_categories', 'id')
                    ->where('company_id', (string) $actor->company_id),
            ],
            'position' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
