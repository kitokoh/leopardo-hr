<?php

declare(strict_types=1);

namespace App\Modules\Growth\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Mise à jour du statut d'un versement partenaire (Growth).
 */
class UpdatePayoutStatusRequest extends FormRequest
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
        return ['status' => ['required', 'in:paid,rejected'], 'notes' => ['nullable', 'string']];
    }
}
