<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Interfaces\Api\V1\Requests;

use App\Rules\NotPrivateUrl;
use Illuminate\Foundation\Http\FormRequest;

/**
 * PublicApplyRequest — validation for anonymous candidate applications
 * submitted through the public careers portal.
 */
class PublicApplyRequest extends FormRequest
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
            'phone' => 'nullable|string|max:20',
            'cover_letter' => 'nullable|string|max:5000',
            // Issue #5588 : garde SSRF — un futur job de parsing CV ne
            // devra jamais fetch un URL privé/réservé (pattern webhooks).
            'resume_url' => ['nullable', 'url', 'max:500', new NotPrivateUrl],
            'resume' => 'nullable|file|mimes:pdf,doc,docx|max:5120',
            'source' => 'nullable|in:website,linkedin,referral,agency,other',
        ];
    }
}
