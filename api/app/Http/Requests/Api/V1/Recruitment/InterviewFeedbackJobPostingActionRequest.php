<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

class InterviewFeedbackJobPostingActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'feedback' => 'required|string|max:5000',
            'rating' => 'nullable|integer|min:1|max:5',
        ];
    }
}
