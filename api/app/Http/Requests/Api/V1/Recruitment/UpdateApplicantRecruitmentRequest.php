<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateApplicantRecruitmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'sometimes|in:new,screening,interview,offer,hired,rejected,withdrawn',
            'rating' => 'nullable|integer|min:1|max:5',
            'notes' => 'nullable|string',
        ];
    }
}
