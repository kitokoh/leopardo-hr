<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1\Requests;

use App\Modules\Accounting\Domain\Enums\ContactSource;
use App\Modules\Accounting\Domain\Enums\ContactType;
use App\Modules\Accounting\Domain\Support\AccountingCurrencies;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un contact client/fournisseur — issue #5222.
 *
 * Les champs sensibles (`tax_id`) sont chiffrés au repos par le cast
 * `encrypted` du modèle ; `metadata` par le cast `encrypted:array`.
 *
 * Devise (issue #5270) : code ISO 4217 parmi le registre `AccountingCurrencies`
 * (union des devises `CountryDefaults`) ; absente à la création, la devise
 * par défaut de l'entreprise est appliquée (settings → pays).
 */
class StoreContactRequest extends FormRequest
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
            'type' => ['required', 'string', 'in:'.implode(',', ContactType::values())],
            'name' => ['required', 'string', 'max:255'],
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
