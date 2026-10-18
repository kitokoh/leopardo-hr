<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Acceptation d'une offre — TRADE-004/#8479.
 */
final class QuoteAcceptRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'expected_delivery_at' => ['nullable', 'date'],
        ];
    }
}
