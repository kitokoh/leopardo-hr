<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Interfaces\Api\V1\Requests;

use App\Modules\FarmOs\Domain\Enums\FarmHealthEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Evenement sanitaire (#9732).
 *
 * `withdrawal_until` (delai d'attente avant abattage/consommation) est une
 * donnee DECLAREE par l'exploitant ou son veterinaire : Farm OS ne la calcule
 * pas et ne la devine pas.
 */
final class StoreFarmHealthEventRequest extends FormRequest
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
            'event_type' => ['required', 'string', Rule::in(FarmHealthEventType::values())],
            'title' => ['required', 'string', 'max:160'],
            'product_name' => ['nullable', 'string', 'max:160'],
            'dosage' => ['nullable', 'string', 'max:80'],
            'administered_at' => ['required', 'date'],
            'next_due_at' => ['nullable', 'date'],
            'withdrawal_until' => ['nullable', 'date'],
            'veterinarian_id' => ['nullable', 'integer'],
            'cost_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'client_health_id' => ['nullable', 'string', 'max:80'],
        ];
    }
}
