<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Recruitment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInterviewRecruitmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'interviewer_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id')->where('company_id', $actor->company_id),
            ],
            'type' => 'required|in:phone,video,onsite,technical',
            'scheduled_at' => 'required|date',
            'duration_minutes' => 'nullable|integer|min:15|max:480',
        ];
    }
}
