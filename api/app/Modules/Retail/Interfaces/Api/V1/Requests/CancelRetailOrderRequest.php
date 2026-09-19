<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Annulation d'une commande POS Retail (BC-17 RETAIL, #7674).
 *
 * Motif optionnel. Une commande completee annulee genere des mouvements
 * `return` qui restaurent le stock (RetailPosService::cancelOrder).
 * L'autorisation est tranchee par RetailOrderPolicy::cancel().
 */
class CancelRetailOrderRequest extends FormRequest
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
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
