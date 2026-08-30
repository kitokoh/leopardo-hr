<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-702 (#6215) — Validation de la demande d'export CSV.
 *
 * `report_type` allowlisté (les colonnes par type sont définies serveur) ;
 * mêmes filtres que les rapports (hérités de RestaurantReportQueryRequest).
 */
class StoreRestaurantReportExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // le contrôleur tranche la permission restaurant.reports
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'report_type' => ['required', 'string', Rule::in(['sales', 'occupancy', 'products', 'cogs', 'pos'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'branch_id' => ['nullable', 'integer'],
        ];
    }
}
