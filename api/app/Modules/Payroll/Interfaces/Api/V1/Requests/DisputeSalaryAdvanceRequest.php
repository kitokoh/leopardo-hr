<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PA2-PAY-015 — Employee opens a dispute ("reclamation") on a salary
 * advance whose payment was declared but not actually received as
 * described (wrong amount, never handed over, wrong recipient, etc.).
 */
class DisputeSalaryAdvanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dispute_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
