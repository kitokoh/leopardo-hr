<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1\Requests;

use App\Modules\Accounting\Domain\Enums\ContactSource;
use App\Modules\Accounting\Domain\Enums\ContactType;
use App\Modules\Accounting\Domain\Support\AccountingCurrencies;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'un contact client/fournisseur — issue #5222.
 *
 * PATCH-like : chaque champ est optionnel (`sometimes`) pour permettre
 * les mises à jour partielles. Devise (issue #5270) : code ISO 4217 parmi
 * le registre `AccountingCurrencies` ; non fournie au PUT, la devise
 * existante est conservée (jamais de surcharge silencieuse).
 */
class UpdateContactRequest extends FormRequest
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
            'type' => ['sometimes', 'string', 'in:'.implode(',', ContactType::values())],
            'name' => ['sometimes', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'currency' => ['nullable', 'string', Rule::in(AccountingCurrencies::supported())],
            'payment_terms' => ['nullable', 'string', 'max:60'],
            'language' => ['nullable', 'string', 'max:10'],
            'source' => ['nullable', 'string', 'in:'.implode(',', ContactSource::values())],
            'marketing_lead_id' => ['nullable', 'integer'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
