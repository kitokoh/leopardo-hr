<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Interfaces\Api\V1\Requests;

use App\Modules\FarmOs\Domain\Enums\FarmProductionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Releve de production (lait, oeufs, laine...) (#9732).
 */
final class StoreFarmProductionRequest extends FormRequest
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
            'production_type' => ['required', 'string', Rule::in(FarmProductionType::values())],
            'quantity' => ['required', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:24'],
            'quality_grade' => ['nullable', 'string', 'max:40'],
            'produced_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'client_production_id' => ['nullable', 'string', 'max:80'],
        ];
    }
}
