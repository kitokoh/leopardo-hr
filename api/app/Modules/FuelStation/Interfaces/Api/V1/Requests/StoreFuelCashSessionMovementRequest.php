<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Modules\FuelStation\Domain\Models\FuelCashSessionMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mouvement de caisse (FUEL-007, #5801) — type in|out, montant strictement
 * positif, motif obligatoire.
 */
class StoreFuelCashSessionMovementRequest extends FormRequest
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
            'type' => ['required', Rule::in(FuelCashSessionMovement::TYPES)],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
