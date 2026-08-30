<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-505 (#6075) — Validation d'une demande d'export CSV.
 * `report_type` allowlisté, période bornée, `idempotency_key` obligatoire.
 */
class StoreTravelExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gate `travel.reports` tranchée dans le contrôleur
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'report_type' => ['required', Rule::in(['sales'])],
            'from' => ['required', 'date', 'before_or_equal:to'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ];
    }
}
