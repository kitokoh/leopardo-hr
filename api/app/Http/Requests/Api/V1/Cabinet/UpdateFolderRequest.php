<?php

namespace App\Http\Requests\Api\V1\Cabinet;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('cabinet_folders', 'id')->where('company_id', $companyId),
            ],
            'color' => ['nullable', 'string', 'max:30'],
            'icon' => ['nullable', 'string', 'max:50'],
        ];
    }
}
