<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Import d'un relevé bancaire CSV (issue #5435).
 */
class BankStatementImportRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'statement_period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'import_reference' => ['required', 'string', 'max:120'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => __('accounting.validation.bank_file_required'),
            'file.mimes' => __('accounting.validation.bank_file_mimes'),
            'statement_period.required' => __('accounting.validation.bank_period_required'),
            'statement_period.regex' => __('accounting.validation.bank_period_format'),
            'import_reference.required' => __('accounting.validation.bank_reference_required'),
        ];
    }
}
