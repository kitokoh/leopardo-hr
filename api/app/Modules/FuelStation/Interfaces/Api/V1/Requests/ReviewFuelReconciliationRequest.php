<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Modules\FuelStation\Domain\Models\FuelReconciliationReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Revue manager d'un rapport de rapprochement (FUEL-009, #5803).
 *
 * L'explication est requise dès qu'un écart existe (validation au niveau
 * service : variance != 0 → explanation obligatoire).
 */
class ReviewFuelReconciliationRequest extends FormRequest
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
            'status' => ['nullable', Rule::in([FuelReconciliationReport::STATUS_REVIEWED, FuelReconciliationReport::STATUS_APPROVED])],
            'explanation' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
