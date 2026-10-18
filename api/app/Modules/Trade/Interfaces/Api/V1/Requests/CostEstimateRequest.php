<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Calcul d'un cout total rendu — TRADE-007/#8482.
 *
 * `fx` est un sous-objet TRACE : `rate`, `date`, `source`. Il est FACULTATIF —
 * sans lui, les postes en devise etrangere sont signales comme non convertis
 * plutot que convertis avec un taux invente.
 *
 * Chaque poste porte sa `source` (`confirmed|estimated|user_input|unavailable`).
 */
final class CostEstimateRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'goods_value_minor' => ['required', 'integer', 'min:0'],
            'goods_currency' => ['required', 'string', 'size:3', 'alpha'],
            'display_currency' => ['required', 'string', 'size:3', 'alpha'],
            'origin_country' => ['nullable', 'string', 'size:2', 'alpha'],
            'destination_country' => ['nullable', 'string', 'size:2', 'alpha'],
            'incoterm' => ['nullable', 'string', 'size:3'],
            'trade_deal_id' => ['nullable', 'integer', 'min:1'],
            'trade_quote_id' => ['nullable', 'integer', 'min:1'],
            'trade_order_id' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:2000'],

            'fx' => ['nullable', 'array'],
            'fx.rate' => ['required_with:fx', 'numeric', 'gt:0'],
            'fx.date' => ['required_with:fx', 'date'],
            'fx.source' => ['required_with:fx', 'string', 'max:40'],

            'lines' => ['nullable', 'array'],
            'lines.*.type' => ['required_with:lines', 'string', 'max:40'],
            'lines.*.amount_minor' => ['required_with:lines', 'integer'],
            'lines.*.currency' => ['required_with:lines', 'string', 'size:3', 'alpha'],
            'lines.*.source' => ['nullable', 'string', 'max:20'],
            'lines.*.label' => ['nullable', 'string', 'max:200'],
            'lines.*.note' => ['nullable', 'string', 'max:300'],
        ];
    }
}
