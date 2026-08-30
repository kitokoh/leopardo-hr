<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPosSession;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTable;
use App\Modules\RestaurantManager\Domain\Models\RestaurantZone;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-402 (#6189) — Validation stricte de création de commande.
 *
 * Règles tenant-scopées (exists BelongsToCompany) : branche, table, zone et
 * session POS doivent appartenir au tenant ; une table `dine_in` doit
 * appartenir à la branche de la commande. `idempotency_key` (uuid) permet le
 * rejeu sans doublon. Aucun montant n'est accepté : les totaux sont
 * recalculés côté serveur.
 */
class StoreRestaurantOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantOrderPolicy::create() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->user();
        $companyId = $user instanceof Employee ? $user->company_id : null;
        $branchId = $this->input('branch_id');

        return [
            'branch_id' => [
                'required',
                'integer',
                Rule::exists((new RestaurantBranch)->getTable(), 'id')->where(
                    fn (Builder $query) => $query->where('company_id', $companyId)
                ),
            ],
            'order_type' => ['required', 'string', Rule::in(['dine_in', 'takeaway', 'delivery'])],
            'table_id' => [
                'nullable',
                'integer',
                'required_if:order_type,dine_in',
                Rule::exists((new RestaurantTable)->getTable(), 'id')->where(function (Builder $query) use ($companyId, $branchId): void {
                    $query->where('company_id', $companyId);
                    if ($branchId !== null) {
                        $query->where('branch_id', $branchId);
                    }
                }),
            ],
            'zone_id' => [
                'nullable',
                'integer',
                Rule::exists((new RestaurantZone)->getTable(), 'id')->where(
                    fn (Builder $query) => $query->where('company_id', $companyId)
                ),
            ],
            'covers' => ['nullable', 'integer', 'min:1', 'max:999'],
            'pos_session_id' => [
                'nullable',
                'integer',
                Rule::exists((new RestaurantPosSession)->getTable(), 'id')->where(function (Builder $query) use ($companyId, $branchId): void {
                    $query->where('company_id', $companyId);
                    if ($branchId !== null) {
                        $query->where('branch_id', $branchId);
                    }
                }),
            ],
            'customer_contact_id' => ['nullable', 'integer'],
            'source' => ['sometimes', 'string', Rule::in(['pos', 'web', 'phone', 'delivery_app'])],
            'note_redacted' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'uuid'],
        ];
    }
}
