<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Social;

use Illuminate\Foundation\Http\FormRequest;

class StoreSocialContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'country_code' => 'required|string|size:2|in:DZ,MA,TN,FR,TR,SN',
            'name' => 'required|string|max:150',
            'code' => 'required|string|max:50|unique:social_contributions,code',
            'type' => 'required|in:employee,employer',
            'rate' => 'required|numeric|min:0|max:100',
            'cap' => 'nullable|numeric|min:0',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after:effective_from',
        ];
    }
}
