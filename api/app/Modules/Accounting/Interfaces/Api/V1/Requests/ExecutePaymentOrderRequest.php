<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Exécution d'un ordre de virement — issue #5239 (Phase C).
 * La référence banque est obligatoire (preuve du rapprochement).
 */
class ExecutePaymentOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RBAC porté par le middleware de route (api.manager:comptable)
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'bank_reference' => ['required', 'string', 'max:120'],
            'executed_at' => ['nullable', 'date'],
        ];
    }
}
