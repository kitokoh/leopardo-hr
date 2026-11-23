<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Requests;

use App\Modules\Trade\Domain\Enums\TradeRfqStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Transition de statut d'une demande de prix — #9779.
 *
 * Le client envoie la cible ; c'est `TradeRfqStateMachine` qui decide si le
 * saut est permis. On valide donc seulement que la cible est un statut CONNU :
 * un statut invente est un 422 de validation, une transition interdite est un
 * 422 metier (`INVALID_STATUS_TRANSITION`) — deux causes distinctes, deux
 * codes distincts, ce qui rend l'ecran capable de dire laquelle.
 *
 * `awarded` exige `awarded_quote_id` : attribuer sans designer l'offre
 * gagnante produirait un etat qui affirme une decision sans la nommer.
 */
final class RfqTransitionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // #9859 — le paramètre de route `rfq` est résolu une fois, avec un
        // type explicite. Avant, `$this->route('rfq')?->id` supposait un
        // objet alors que le binding peut rendre un `string` (id brut, ou
        // route résolue avant le model binding) : PHPStan signalait
        // `property.notFound` sur `(object|string)::$id`, et à l'exécution la
        // contrainte d'appartenance pouvait être silencieusement perdue.
        $rfq = $this->route('rfq');
        $rfqId = $rfq instanceof Model ? $rfq->getKey() : $rfq;

        return [
            'status' => ['required', 'string', Rule::in(TradeRfqStatus::values())],
            // L'offre gagnante doit appartenir a CETTE RFQ : sans cette regle,
            // on pourrait attribuer une consultation a l'offre d'une autre.
            'awarded_quote_id' => [
                'required_if:status,awarded',
                'nullable',
                'integer',
                Rule::exists('trade_quotes', 'id')->where('trade_rfq_id', $rfqId),
            ],
            'close_reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'awarded_quote_id.required_if' => __('trade.rfqs.awarded_quote_required'),
            'awarded_quote_id.exists' => __('trade.rfqs.awarded_quote_invalid'),
        ];
    }
}
