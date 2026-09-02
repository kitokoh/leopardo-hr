<?php

declare(strict_types=1);

namespace App\Modules\Growth\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Mise à jour du taux de commission d'un partenaire (Growth).
 */
class UpdatePartnerRateRequest extends FormRequest
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
        return ['rate' => ['required', 'integer', 'min:0', 'max:10000'], 'reason' => ['required', 'string', 'min:5']];
    }
}
