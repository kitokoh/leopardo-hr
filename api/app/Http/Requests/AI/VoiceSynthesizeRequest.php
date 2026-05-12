<?php

declare(strict_types=1);

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;

class VoiceSynthesizeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'text' => 'required|string|max:2000',
            'language' => 'nullable|in:fr,ar,tr,en',
            'voice' => 'nullable|string|max:50',
        ];
    }
}
