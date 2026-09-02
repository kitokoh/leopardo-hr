<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-409 (#6196) — Validation stricte d'ouverture d'une session de table.
 *
 * `covers` (nombre de couverts) optionnel ; `order_id` optionnel et
 * tenant-scopé. L'autorisation est tranchée par
 * `RestaurantTableSessionPolicy::create()`.
 */
class OpenRestaurantTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantTableSessionPolicy::create() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->user();
        $companyId = $user instanceof Employee ? $user->company_id : null;

        return [
            'covers' => ['nullable', 'integer', 'min:1', 'max:999'],
            'order_id' => [
                'nullable',
                'integer',
                Rule::exists((new RestaurantOrder)->getTable(), 'id')->where(
                    fn (Builder $query) => $query->where('company_id', $companyId)
                ),
            ],
        ];
    }
}
