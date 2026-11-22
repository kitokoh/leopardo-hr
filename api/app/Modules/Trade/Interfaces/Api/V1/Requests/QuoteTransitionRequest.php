<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Requests;

use App\Modules\Trade\Domain\Enums\TradeQuoteStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Transition de statut d'une offre commerciale — #9779.
 *
 * `accepted` est VOLONTAIREMENT exclu de la liste : accepter une offre cree
 * une commande (`AcceptQuoteAction`). L'autoriser ici permettrait de marquer
 * une offre « acceptee » sans qu'aucune commande n'existe — un etat qui ment
 * sur ce qui s'est passe. L'ecran doit appeler `POST /quotes/{quote}/accept`.
 */
final class QuoteTransitionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $allowed = array_values(array_filter(
            TradeQuoteStatus::values(),
            static fn (string $status): bool => $status !== TradeQuoteStatus::Accepted->value,
        ));

        return [
            'status' => ['required', 'string', Rule::in($allowed)],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => __('trade.quotes.accept_has_its_own_route'),
        ];
    }
}
