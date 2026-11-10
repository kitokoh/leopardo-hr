<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1\Requests;

use App\Modules\Accounting\Domain\Enums\JournalType;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Création d'un journal comptable — issue #9370.
 *
 * Le code FEC est borné à 6 caractères (largeur de la colonne
 * `accounting_journals.code`) : un code plus long serait tronqué en base et
 * casserait la correspondance avec `accounting_journal_entries.journal_code`.
 */
class AccountingJournalRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'min:2', 'max:6', 'regex:/^[A-Za-z0-9]+$/'],
            'label' => ['required', 'string', 'max:120'],
            'type' => ['required', 'string', 'in:'.implode(',', JournalType::values())],
            // Compte de trésorerie par défaut : requis pour banque/caisse,
            // toléré ailleurs (journal de vente sans compte de trésorerie).
            'default_account_code' => ['nullable', 'string', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => __('accounting.errors.journal_code_required'),
            'code.regex' => __('accounting.errors.journal_code_format'),
            'code.max' => __('accounting.errors.journal_code_format'),
            'type.in' => __('accounting.errors.journal_type_invalid'),
        ];
    }
}
