<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Retail\Domain\Models\RetailLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'un emplacement de stock du module Retail (BC-17 RETAIL, #7673).
 *
 * Code unique par tenant, hors emplacement courant.
 */
class UpdateRetailLocationRequest extends FormRequest
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

        $location = $this->route('location');
        $locationId = $location instanceof RetailLocation ? (int) $location->getKey() : (int) $location;

        return [
            'name' => ['required', 'string', 'max:160'],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:40',
                Rule::unique('retail_locations', 'code')
                    ->where('company_id', (string) $actor->company_id)
                    ->ignore($locationId),
            ],
            'type' => ['nullable', Rule::in(['store', 'warehouse'])],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
