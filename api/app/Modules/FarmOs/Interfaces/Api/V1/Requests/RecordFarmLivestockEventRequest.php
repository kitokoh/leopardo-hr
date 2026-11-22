<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Interfaces\Api\V1\Requests;

use App\Modules\FarmOs\Domain\Enums\FarmLivestockEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mouvement de cheptel (entree, sortie, mortalite) (#9732).
 */
final class RecordFarmLivestockEventRequest extends FormRequest
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
            'event_type' => ['required', 'string', Rule::in(FarmLivestockEventType::values())],
            'quantity' => ['required', 'integer', 'min:1'],
            'counterparty_id' => ['nullable', 'integer'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'reason' => ['nullable', 'string', 'max:200'],
            'client_event_id' => ['nullable', 'string', 'max:80'],
            'occurred_at' => ['nullable', 'date'],
        ];
    }
}
