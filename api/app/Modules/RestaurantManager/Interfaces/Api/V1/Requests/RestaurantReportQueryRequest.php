<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-701/702 (#6214/#6215) — Filtres communs des rapports restaurant.
 *
 * `from`/`to` en ISO-8601 (datetime) ou date simple ; `branch_id` tenant-scopé
 * optionnel (= toutes branches). Les bornes sont recalculées serveur
 * (RestaurantReportService) — aucun filtre n'accepte de totaux du client.
 */
class RestaurantReportQueryRequest extends FormRequest
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
        $companyId = $this->companyId();

        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'branch_id' => ['nullable', 'integer', Rule::exists((new RestaurantBranch)->getTable(), 'id')->where(fn (Builder $query) => $query->where('company_id', $companyId))],
        ];
    }

    private function companyId(): ?string
    {
        $user = $this->user();
        if ($user instanceof Employee && $user->company_id !== null) {
            return $user->company_id;
        }

        return app()->bound('current_company') ? currentCompany()->id : null;
    }
}
