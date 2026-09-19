<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Annulation d'une vente comptoir — PHARMA-005 (#7802). Raison OBLIGATOIRE
 * (piste comptable : la vente est conservée `voided`, jamais supprimée).
 */
class VoidPharmacySaleRequest extends FormRequest
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
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
