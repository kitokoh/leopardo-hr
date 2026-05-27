<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Training;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSessionTrainingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'trainer_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id')->where('company_id', $this->user()->company_id),
            ],
            'external_trainer' => 'nullable|string|max:200',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date',
            'location' => 'nullable|string|max:200',
            'status' => 'sometimes|in:planned,in_progress,completed,cancelled',
            'notes' => 'nullable|string',
        ];
    }
}
