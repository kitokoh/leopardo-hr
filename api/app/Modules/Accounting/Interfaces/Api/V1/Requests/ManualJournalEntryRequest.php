<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Écriture manuelle (journal d'opérations diverses) — issue #9369.
 *
 * L'équilibre débit/crédit n'est **pas** validé ici mais dans le service : la
 * tolérance (0,005) et le message d'erreur sont ceux du posting dérivé, pour que
 * l'utilisateur reçoive un diagnostic identique quelle que soit l'origine de
 * l'écriture. Le formulaire ne vérifie que la forme.
 */
class ManualJournalEntryRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'entry_date' => ['required', 'date_format:Y-m-d'],
            'description' => ['nullable', 'string', 'max:500'],
            'piece' => ['nullable', 'string', 'max:64'],
            'journal_code' => ['nullable', 'string', 'max:6'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_code' => ['required', 'string', 'max:20'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'lines.min' => __('accounting.errors.manual_needs_two_lines'),
            'lines.*.account_code.required' => __('accounting.errors.manual_account_required'),
        ];
    }
}
