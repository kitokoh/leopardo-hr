<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Interfaces\Api\V1\Requests;

use App\Modules\FarmOs\Domain\Enums\FarmReproductionEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Evenement de reproduction (#9732).
 */
final class StoreFarmReproductionEventRequest extends FormRequest
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
            'event_type' => ['required', 'string', Rule::in(FarmReproductionEventType::values())],
            'occurred_at' => ['required', 'date'],
            'gestation_days' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'expected_due_at' => ['nullable', 'date'],
            'offspring_count' => ['nullable', 'integer', 'min:0'],
            'sire_animal_id' => ['nullable', 'integer'],
            'method' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'client_reproduction_id' => ['nullable', 'string', 'max:80'],
        ];
    }
}
