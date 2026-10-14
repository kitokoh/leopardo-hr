<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Emission d'une offre commerciale — TRADE-004/#8479.
 *
 * AUCUN TOTAL N'EST ACCEPTE DU CLIENT : seuls les prix unitaires et les
 * quantites le sont, le serveur calcule les totaux. Un champ `total_minor`
 * envoye serait simplement ignore — c'est ce qui garantit que le montant
 * affiche correspond toujours aux lignes reellement saisies.
 */
final class QuoteStoreRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'incoterm' => ['nullable', 'string', 'size:3'],
            'validity_until' => ['nullable', 'date', 'after_or_equal:today'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'payment_terms' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:2000'],

            'trade_rfq_id' => ['nullable', 'integer', 'min:1'],
            'trade_rfq_invitation_id' => ['nullable', 'integer', 'min:1'],
            'trade_deal_id' => ['nullable', 'integer', 'min:1'],
            'trade_counterparty_id' => ['nullable', 'integer', 'min:1'],

            'submit' => ['nullable', 'boolean'],

            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:300'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit' => ['nullable', 'string', 'max:20'],
            'lines.*.unit_price_minor' => ['required', 'integer', 'min:0'],
            'lines.*.trade_product_id' => ['nullable', 'integer', 'min:1'],
            'lines.*.moq_quantity' => ['nullable', 'numeric', 'gt:0'],
            'lines.*.lead_time_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
        ];
    }
}
