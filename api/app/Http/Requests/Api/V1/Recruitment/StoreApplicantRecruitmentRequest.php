<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

class StoreApplicantRecruitmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:30',
            'source' => 'nullable|in:website,referral,linkedin,agency,other',
            'cover_letter' => 'nullable|string',
            'notes' => 'nullable|string',
        ];
    }
}
