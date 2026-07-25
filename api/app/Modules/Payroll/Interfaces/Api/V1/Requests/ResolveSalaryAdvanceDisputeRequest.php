<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PA2-PAY-015 — Manager resolves a previously-opened dispute, either by
 * confirming the employee actually received the payment (`confirmed`) or
 * by reopening the payment workflow because the dispute was legitimate
 * (`reopened`, sends the advance back to `payment_declared` so the
 * manager can correct the payment and the employee can confirm again).
 */
class ResolveSalaryAdvanceDisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'resolution' => ['required', Rule::in(['confirmed', 'reopened'])],
            'dispute_resolution_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
