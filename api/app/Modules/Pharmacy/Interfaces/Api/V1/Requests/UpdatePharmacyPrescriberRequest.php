<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Interfaces\Api\V1\Requests;

use App\Modules\Pharmacy\Domain\Models\PharmacyPrescriber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'un prescripteur — PHARMA-006 (#7803).
 */
class UpdatePharmacyPrescriberRequest extends FormRequest
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
            'full_name' => ['sometimes', 'string', 'max:191'],
            'registration_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'specialty' => ['sometimes', 'nullable', 'string', 'max:100'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'status' => ['sometimes', Rule::in(PharmacyPrescriber::STATUSES)],
        ];
    }
}
