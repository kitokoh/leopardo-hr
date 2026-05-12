<?php

declare(strict_types=1);

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;

class VoiceTranscribeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'audio' => 'required|file|mimes:wav,mp3,webm,ogg,m4a|max:10240',
            'language' => 'nullable|in:fr,ar,tr,en',
        ];
    }
}
