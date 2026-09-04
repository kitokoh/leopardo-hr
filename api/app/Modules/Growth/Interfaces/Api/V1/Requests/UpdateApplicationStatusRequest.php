<?php

declare(strict_types=1);

namespace App\Modules\Growth\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Mise à jour du statut d'une candidature partenaire (Growth).
 */
class UpdateApplicationStatusRequest extends FormRequest
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
        return ['status' => ['required', 'in:approved,rejected']];
    }
}
