<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Conversion d'un dossier d'admission en élève (EDU-004, #5820 / EDU-010).
 *
 * La conversion est idempotente ; les données sont optionnelles (surcharge
 * `metadata` uniquement) — le consentement est déjà porté par le dossier.
 */
class ConvertEduAdmissionRequest extends FormRequest
{
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
            'metadata' => ['nullable', 'array', 'max:20'],
        ];
    }
}
