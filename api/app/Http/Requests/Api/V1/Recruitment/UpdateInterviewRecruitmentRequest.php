<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInterviewRecruitmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'sometimes|in:scheduled,completed,cancelled,no_show',
            'feedback' => 'nullable|string',
            'rating' => 'nullable|integer|min:1|max:5',
        ];
    }
}
