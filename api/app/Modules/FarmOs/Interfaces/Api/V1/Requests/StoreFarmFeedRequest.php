<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Consommation d'aliment (#9732).
 */
final class StoreFarmFeedRequest extends FormRequest
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
            'feed_name' => ['required', 'string', 'max:160'],
            'farm_input_id' => ['nullable', 'integer'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:24'],
            'cost_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'fed_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'client_feed_id' => ['nullable', 'string', 'max:80'],
        ];
    }
}
