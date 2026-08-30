<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * TRAVEL-907 (#6110) — Rejet d'une annonce (motif obligatoire, borné).
 */
class ModerateTravelAdvertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TravelAdvertPolicy::moderate() tranche
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:1', 'max:500'],
        ];
    }
}
