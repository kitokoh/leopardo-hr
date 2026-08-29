<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-305 (#6035) — Validation stricte de modification d'une classe de
 * service.
 */
class UpdateTravelClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TravelClassPolicy::update() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['sometimes', 'string', 'max:40'],
            'label' => ['sometimes', 'string', 'max:120'],
            'color' => ['nullable', 'string', 'max:7'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'disabled'])],
        ];
    }
}
