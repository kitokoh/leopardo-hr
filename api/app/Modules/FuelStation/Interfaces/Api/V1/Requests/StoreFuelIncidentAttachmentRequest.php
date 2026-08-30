<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Pièce jointe d'incident contrôlée (FUEL-010, #5804).
 *
 * Allowlist mime (images, PDF, textes — aucune exécution possible),
 * taille plafonnée à 5 Mo, stockage interne privé.
 */
class StoreFuelIncidentAttachmentRequest extends FormRequest
{
    private const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
        'text/plain',
        'text/csv',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:5120', 'mimetypes:'.implode(',', self::ALLOWED_MIMES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimetypes' => 'FUEL_ATTACHMENT_MIME_NOT_ALLOWED',
            'file.max' => 'FUEL_ATTACHMENT_TOO_LARGE',
        ];
    }
}
