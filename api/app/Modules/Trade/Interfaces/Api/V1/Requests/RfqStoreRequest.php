<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Creation d'une demande de prix (RFQ) — BC-36 TRADE, TRADE-003/#8478.
 *
 * Au moins une ligne est EXIGEE (`lines` min:1) : une RFQ sans ligne serait un
 * objet vide qui ne peut rien produire. Chaque ligne porte soit un produit
 * catalogue, soit une description libre — jamais les deux obligatoirement.
 *
 * Le montant est en MINOR UNITS (entier) : aucun flottant ne traverse l'API
 * pour representer de l'argent.
 */
final class RfqStoreRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'destination_country' => ['nullable', 'string', 'size:2', 'alpha'],
            'destination_port' => ['nullable', 'string', 'max:120'],
            'incoterm' => ['nullable', 'string', 'size:3'],
            'budget_minor' => ['nullable', 'integer', 'min:0'],
            'budget_currency' => ['nullable', 'string', 'size:3', 'alpha'],
            'deadline_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'trade_deal_id' => ['nullable', 'integer', 'min:1'],

            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:300'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit' => ['nullable', 'string', 'max:20'],
            'lines.*.trade_product_id' => ['nullable', 'integer', 'min:1'],
            'lines.*.target_unit_price_minor' => ['nullable', 'integer', 'min:0'],
            'lines.*.specs' => ['nullable', 'array'],
        ];
    }
}
