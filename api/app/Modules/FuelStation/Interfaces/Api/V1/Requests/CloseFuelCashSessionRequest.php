<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Clôture d'une session de caisse (FUEL-007, #5801).
 *
 * Seul le montant COMPTÉ (closing_balance) est fourni par le pompiste ;
 * expected_balance et variance sont calculés côté serveur.
 */
class CloseFuelCashSessionRequest extends FormRequest
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
            'closing_balance' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
        ];
    }
}
