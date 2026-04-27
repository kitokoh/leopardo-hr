<?php

namespace App\Modules\Cameras\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TestRtspRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rtsp_url' => [
                'required',
                'string',
                'max:1000',
                'regex:#^rtsp://[^\s\x00-\x1F"\'<>]+$#i',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'rtsp_url.regex' => 'The rtsp_url must be a valid RTSP URL starting with rtsp://',
        ];
    }
}
