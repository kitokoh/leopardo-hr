<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * #5805 — Création d'une station (FUEL-011).
 */
class StoreFuelStationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la Policy est appliquée par le contrôleur
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9\-_]+$/'],
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'currency' => ['nullable', 'string', 'max:10'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
        ];
    }
}
