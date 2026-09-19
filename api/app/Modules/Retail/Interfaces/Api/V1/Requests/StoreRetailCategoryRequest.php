<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'une catégorie du module Retail (BC-17 RETAIL, #7672).
 *
 * Slug unique par tenant (généré automatiquement depuis le nom si absent).
 */
class StoreRetailCategoryRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:160'],
            'slug' => [
                'nullable',
                'alpha_dash',
                'max:180',
                Rule::unique('retail_categories', 'slug')
                    ->where('company_id', (string) $actor->company_id),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('retail_categories', 'id')
                    ->where('company_id', (string) $actor->company_id),
            ],
            'position' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
