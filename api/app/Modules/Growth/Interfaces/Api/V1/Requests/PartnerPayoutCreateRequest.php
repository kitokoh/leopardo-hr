<?php

declare(strict_types=1);

namespace App\Modules\Growth\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Demande de versement partenaire (Growth).
 */
class PartnerPayoutCreateRequest extends FormRequest
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
        return ['amount' => ['required', 'integer', 'min:100'], 'currency' => ['required', 'string', 'size:3']];
    }
}
