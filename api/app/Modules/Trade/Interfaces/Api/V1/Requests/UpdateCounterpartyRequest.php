<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Requests;

use App\Modules\Trade\Domain\Enums\TradeCounterpartyRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise a jour d'une contrepartie — #9779.
 *
 * Tous les champs sont `sometimes` : le client n'envoie que ce qu'il change.
 * `verification_status` n'est PAS editable ici — verifier une contrepartie est
 * un acte distant et trace (qui, quand), servi par un endpoint dedie ; un `PUT`
 * ne doit pas pouvoir faire passer une contrepartie pour verifiee.
 */
final class UpdateCounterpartyRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'country' => ['sometimes', 'nullable', 'string', 'size:2', 'alpha'],
            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'email' => ['sometimes', 'nullable', 'email', 'max:190'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'website' => ['sometimes', 'nullable', 'url', 'max:255'],
            'roles' => ['sometimes', 'required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(TradeCounterpartyRole::values())],
            'capabilities' => ['sometimes', 'nullable', 'array'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'crm_account_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
