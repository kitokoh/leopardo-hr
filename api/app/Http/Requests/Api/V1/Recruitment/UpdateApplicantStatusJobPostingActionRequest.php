<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateApplicantStatusJobPostingActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:new,screening,interview,offer,hired,rejected,withdrawn',
        ];
    }
}
