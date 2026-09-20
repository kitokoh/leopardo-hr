<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HealthManager\Domain\Models\HealthStaffRole;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Attribution d'un rôle opérationnel (réception, facturation) — HC-002
 * (#7786). Employé RH du MÊME tenant ; rôle borné (reception|billing) et
 * unique par employé + rôle + tenant (422 sur doublon).
 */
class StoreHealthStaffRoleRequest extends FormRequest
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
        /** @var Employee|null $actor */
        $actor = $this->user();

        return [
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'role' => [
                'required',
                Rule::in(HealthStaffRole::ROLES),
                Rule::unique('health_staff_roles', 'role')->where(
                    fn (Builder $query): Builder => $query
                        ->where('company_id', $actor?->company_id)
                        ->where('employee_id', $this->integer('employee_id'))
                ),
            ],
        ];
    }
}
