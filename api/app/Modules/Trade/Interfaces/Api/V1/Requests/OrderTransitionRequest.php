<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Requests;

use App\Modules\Trade\Domain\Enums\TradeOrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Transition de statut d'une commande — #9779.
 *
 * Le client envoie la cible ; c'est `TradeOrderStateMachine` qui decide si le
 * saut est permis. On valide donc seulement que la cible est un statut CONNU :
 * un statut invente est un 422 de validation, une transition interdite est un
 * 422 metier (`INVALID_STATUS_TRANSITION`) — deux causes distinctes, deux
 * codes distincts, ce qui rend l'ecran capable de dire laquelle.
 *
 * `cancelled` exige un motif : une annulation sans raison ecrite est
 * inexploitable six mois plus tard, quand il faut expliquer la commande.
 */
final class OrderTransitionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(TradeOrderStatus::values())],
            'cancel_reason' => ['required_if:status,cancelled', 'nullable', 'string', 'max:500'],
            'expected_delivery_at' => ['nullable', 'date', 'after:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cancel_reason.required_if' => __('trade.orders.cancel_reason_required'),
        ];
    }
}
