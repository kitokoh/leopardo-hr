<?php

namespace App\Http\Requests\Api\V1\Cameras;

use Illuminate\Foundation\Http\FormRequest;

class StoreCameraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'rtsp_url' => [
                'required',
                'string',
                'max:1000',
                'regex:#^rtsp://[^\s\x00-\x1F"\'<>]+$#i',
            ],
            'location' => ['nullable', 'string', 'max:200'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'stream_path_override' => ['nullable', 'string', 'max:100'],
            'metadata' => ['nullable', 'array'],
            'metadata.brand' => ['sometimes', 'string', 'max:60'],
            'metadata.model' => ['sometimes', 'string', 'max:80'],
            'metadata.resolution' => ['sometimes', 'string', 'max:30'],
        ];
    }

    public function messages(): array
    {
        return [
            'rtsp_url.regex' => 'The rtsp_url must be a valid RTSP URL starting with rtsp://',
        ];
    }
}
