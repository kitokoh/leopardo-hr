<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1\Requests;

use App\Modules\Accounting\Domain\Enums\JournalType;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Modification d'un journal comptable — issue #9370.
 *
 * Le **code est volontairement absent** des règles : il est immuable, car les
 * écritures le référencent (`accounting_journal_entries.journal_code`) et un
 * changement orphelinerait l'historique déjà exporté.
 */
class AccountingJournalUpdateRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'string', 'max:120'],
            'type' => ['sometimes', 'string', 'in:'.implode(',', JournalType::values())],
            'default_account_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
