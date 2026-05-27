<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Contract;

use Illuminate\Foundation\Http\FormRequest;

class StoreAmendmentContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amendment_type' => 'required|in:salary_change,position_change,hours_change,renewal,other',
            'changes' => 'required|array',
            'effective_date' => 'required|date',
            'reason' => 'nullable|string',
        ];
    }
}
