<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Social;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSocialContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:150',
            'type' => 'sometimes|in:employee,employer',
            'rate' => 'sometimes|numeric|min:0|max:100',
            'cap' => 'nullable|numeric|min:0',
            'effective_from' => 'sometimes|date',
            'effective_to' => 'nullable|date',
        ];
    }
}
