<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un emplacement de stock du module Retail (BC-17 RETAIL, #7673).
 *
 * Code unique par tenant, type `store|warehouse`.
 */
class StoreRetailLocationRequest extends FormRequest
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
            'code' => [
                'required',
                'string',
                'max:40',
                Rule::unique('retail_locations', 'code')
                    ->where('company_id', (string) $actor->company_id),
            ],
            'type' => ['nullable', Rule::in(['store', 'warehouse'])],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
