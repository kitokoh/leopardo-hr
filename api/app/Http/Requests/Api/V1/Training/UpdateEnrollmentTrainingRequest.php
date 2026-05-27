<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Training;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEnrollmentTrainingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'sometimes|in:enrolled,attended,completed,no_show,cancelled',
            'score' => 'nullable|numeric|min:0|max:100',
            'feedback' => 'nullable|string',
        ];
    }
}
