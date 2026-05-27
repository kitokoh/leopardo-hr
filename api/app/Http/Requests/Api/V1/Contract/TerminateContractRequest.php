<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Contract;

use Illuminate\Foundation\Http\FormRequest;

class TerminateContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'termination_reason' => 'required|string|max:500',
        ];
    }
}
