<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Diffusion d'une RFQ a des fournisseurs — TRADE-003/#8478.
 *
 * `counterparty_ids` min:1 : diffuser a personne ne ferait rien et masquerait
 * une erreur de saisie derriere un succes apparent (la RFQ passerait `open`
 * sans qu'aucun fournisseur ne soit sollicite).
 */
final class RfqSendRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'counterparty_ids' => ['required', 'array', 'min:1'],
            'counterparty_ids.*' => ['integer', 'min:1'],
            'message' => ['nullable', 'string', 'max:2000'],
            'token_lifetime_days' => ['nullable', 'integer', 'min:1', 'max:180'],
        ];
    }
}
