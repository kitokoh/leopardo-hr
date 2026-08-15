<?php

declare(strict_types=1);

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
                // Schémas validés par CameraService::testRtsp (invalid_url) —
                // la regex ne bloque ici que les caractères de contrôle/quoting
                // (défense en profondeur avant construction de la commande ffprobe).
                'regex:#^[a-z][a-z0-9+.-]*://[^\s\x00-\x1F"\'<>]+$#i',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'rtsp_url.regex' => 'The rtsp_url must be a valid URL.',
        ];
    }
}
