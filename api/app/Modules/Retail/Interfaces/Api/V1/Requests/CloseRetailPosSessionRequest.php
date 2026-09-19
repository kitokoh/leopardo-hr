<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Cloture d'une session de caisse POS Retail (BC-17 RETAIL, #7674).
 *
 * `counted_cash_minor` (minor units, entier >= 0) est compare a l'attendu
 * calcule serveur (fonds d'ouverture + paiements cash captures de la
 * session) ; l'ecart signe est persiste avec son motif optionnel.
 * L'autorisation est tranchee par RetailPosSessionPolicy::close().
 */
class CloseRetailPosSessionRequest extends FormRequest
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
            'counted_cash_minor' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'variance_reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
