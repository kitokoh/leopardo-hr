<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Modules\FuelStation\Domain\Models\FuelPump;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création / remplacement d'une pompe (FUEL-011, #5805).
 */
class SaveFuelPumpRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:40'],
            'product_types' => ['required', 'array', 'min:1', 'max:12'],
            'product_types.*' => ['required', 'string', 'max:40'],
            'status' => ['required', Rule::in(FuelPump::STATUSES)],
        ];
    }
}
