<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Training;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSessionTrainingRequest extends FormRequest
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
                Rule::exists('employees', 'id')->where('company_id', $actor->company_id),
            ],
            'external_trainer' => 'nullable|string|max:200',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'location' => 'nullable|string|max:200',
            'notes' => 'nullable|string',
        ];
    }
}
