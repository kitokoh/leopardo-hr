<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * AI-002 (#6771) — soumission d'une photo de compteur pour OCR.
 * Multipart : image (jpg/jpeg/png/webp, ≤ 5 Mo) + clé d'idempotence
 * (parité StoreMeterReadingRequest) + shift optionnel.
 */
class SubmitMeterOcrRequest extends FormRequest
{
    public function authorize(): bool
    {
        // RBAC : employé authentifié du tenant (vérifié dans le contrôleur).
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'shift_id' => ['nullable', 'integer', 'min:1'],
            // Borné à 100 : la colonne correlation_id (fuel_meter_ocr_requests)
            // est string(100) — un rejet silencieux à l'insertion serait un 500.
            'idempotency_key' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9\-_.]{8,100}$/'],
        ];
    }
}
