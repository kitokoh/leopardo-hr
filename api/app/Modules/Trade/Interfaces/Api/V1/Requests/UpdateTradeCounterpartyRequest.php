<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Requests;

use App\Modules\Trade\Domain\Enums\TradeCounterpartyRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'une contrepartie Trade — BC-36 TRADE, #9780.
 *
 * Tous les champs sont `sometimes` : un `PUT` partiel ne doit pas effacer ce
 * qui n'est pas envoyé. `verification_status` reste **absent** — la
 * vérification est un acte distinct et tracé (`POST .../verify`), jamais un
 * champ qu'on modifie au passage.
 */
final class UpdateTradeCounterpartyRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'country' => ['sometimes', 'nullable', 'string', 'size:2', 'alpha'],
            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'email' => ['sometimes', 'nullable', 'email', 'max:190'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'website' => ['sometimes', 'nullable', 'url', 'max:255'],
            'roles' => ['sometimes', 'required', 'array', 'min:1'],
            'roles.*' => ['required', 'string', Rule::in(TradeCounterpartyRole::values())],
            'capabilities' => ['sometimes', 'nullable', 'array'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'crm_account_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'roles.min' => 'Une contrepartie doit porter au moins un rôle métier.',
            'roles.*.in' => 'Rôle inconnu : seuls les rôles du registre Trade sont acceptés.',
        ];
    }
}
