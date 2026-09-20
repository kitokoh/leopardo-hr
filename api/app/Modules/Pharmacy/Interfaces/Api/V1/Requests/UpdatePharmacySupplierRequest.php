<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Interfaces\Api\V1\Requests;

use App\Modules\Pharmacy\Domain\Models\PharmacySupplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'un fournisseur — PHARMA-004 (#7801).
 */
class UpdatePharmacySupplierRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:191'],
            'type' => ['sometimes', Rule::in(PharmacySupplier::TYPES)],
            'contact_name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'email' => ['sometimes', 'nullable', 'email', 'max:191'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(PharmacySupplier::STATUSES)],
        ];
    }
}
