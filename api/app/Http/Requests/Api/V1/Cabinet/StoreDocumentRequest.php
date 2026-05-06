<?php

namespace App\Http\Requests\Api\V1\Cabinet;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
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
        $actor = $this->user();
        $companyId = $actor?->company_id;

        return [
            'file' => ['required', 'file', 'max:20480'],
            'folder_id' => [
                'nullable',
                'integer',
                Rule::exists('cabinet_folders', 'id')
                    ->where('employee_id', $actor?->id)
                    ->where('company_id', $companyId),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
