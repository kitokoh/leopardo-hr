<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Resolution d'une panne (#9732).
 */
final class ResolveFarmBreakdownRequest extends FormRequest
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
            'cost_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'downtime_hours' => ['nullable', 'numeric', 'min:0'],
            'resolved_at' => ['nullable', 'date'],
        ];
    }
}
