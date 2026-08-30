<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * #5803 — Lancement du rapprochement stock d'une période (FUEL-009).
 */
class ReconcileFuelStockRequest extends FormRequest
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
            'period' => ['required', 'string', 'regex:/^20\d{2}-(0[1-9]|1[0-2])$/'],
        ];
    }
}
