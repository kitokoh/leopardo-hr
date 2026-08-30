<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un produit du catalogue FuelStation (FUEL-011, #5805).
 * Code unique par tenant ; unité l|gal ; metadata chiffrée au repos.
 */
class StoreFuelProductRequest extends FormRequest
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
        /** @var Employee|null $actor */
        $actor = $this->user();

        return [
            'code' => [
                'required',
                'string',
                'max:40',
                Rule::unique('fuel_products', 'code')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'name' => ['required', 'string', 'max:150'],
            'unit_code' => ['nullable', Rule::in(['l', 'gal'])],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
