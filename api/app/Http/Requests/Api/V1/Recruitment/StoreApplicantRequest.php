<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

class StoreApplicantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'job_posting_id' => 'required|integer|exists:job_postings,id',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'resume_url' => 'nullable|url|max:500',
            'cover_letter' => 'nullable|string|max:5000',
            'source' => 'nullable|in:website,linkedin,referral,agency,other',
        ];
    }
}
