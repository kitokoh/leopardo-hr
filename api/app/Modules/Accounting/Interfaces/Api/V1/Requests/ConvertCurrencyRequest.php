<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1\Requests;

use App\Modules\Accounting\Domain\Support\AccountingCurrencies;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Conversion d'un montant — issue #5270.
 *
 * `rate` est le taux de change de 1 unité de `from_currency` exprimé dans
 * `to_currency` (multiplication). Il est optionnel quand les devises sont
 * identiques (conversion identité) ; obligatoire sinon (pas de provider
 * réseau en v1 — un taux ne se devine jamais).
 */
class ConvertCurrencyRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'gt:0'],
            'from_currency' => ['required', 'string', Rule::in(AccountingCurrencies::supported())],
            'to_currency' => ['required', 'string', Rule::in(AccountingCurrencies::supported())],
            'rate' => ['nullable', 'numeric', 'gt:0'],
        ];
    }

    /**
     * Garde croisée : un taux manuel est exigé dès que les devises diffèrent
     * (le convertisseur ne fabrique jamais de taux — fail-closed).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $from = strtoupper(trim((string) $this->input('from_currency')));
            $to = strtoupper(trim((string) $this->input('to_currency')));
            $rate = $this->input('rate');

            if ($from !== '' && $from !== $to && ($rate === null || $rate === '')) {
                $validator->errors()->add('rate', 'Le taux de change est requis quand les devises diffèrent.');
            }
        });
    }
}
