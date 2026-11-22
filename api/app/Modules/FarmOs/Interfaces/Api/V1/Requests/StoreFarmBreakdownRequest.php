<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Interfaces\Api\V1\Requests;

use App\Modules\FarmOs\Domain\Enums\FarmBreakdownSeverity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Declaration d'une panne — gravite DECLAREE, aucun diagnostic automatise
 * (#9732).
 */
final class StoreFarmBreakdownRequest extends FormRequest
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
            'severity' => ['required', 'string', Rule::in(FarmBreakdownSeverity::values())],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'occurred_at' => ['required', 'date'],
            'downtime_hours' => ['nullable', 'numeric', 'min:0'],
            'cost_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'client_breakdown_id' => ['nullable', 'string', 'max:80'],
        ];
    }
}
