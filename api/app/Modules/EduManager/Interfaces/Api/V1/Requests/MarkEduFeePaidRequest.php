<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Règlement d'un frais scolaire (EDU-016, #5832).
 *
 * Idempotent : un frais déjà réglé ne change pas (terminal → 422).
 */
class MarkEduFeePaidRequest extends FormRequest
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
            'payment_reference' => ['nullable', 'string', 'max:120'],
        ];
    }
}
