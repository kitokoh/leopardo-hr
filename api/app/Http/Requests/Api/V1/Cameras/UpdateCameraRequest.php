<?php

namespace App\Http\Requests\Api\V1\Cameras;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCameraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'rtsp_url' => [
                'sometimes',
                'required',
                'string',
                'max:1000',
                'regex:#^rtsp://[^\s\x00-\x1F"\'<>]+$#i',
            ],
            'location' => ['sometimes', 'nullable', 'string', 'max:200'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'stream_path_override' => ['sometimes', 'nullable', 'string', 'max:100'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'rtsp_url.regex' => 'The rtsp_url must be a valid RTSP URL starting with rtsp://',
        ];
    }
}
