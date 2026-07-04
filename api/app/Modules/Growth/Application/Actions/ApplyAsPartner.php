<?php

declare(strict_types=1);

namespace App\Modules\Growth\Application\Actions;

use App\Modules\Growth\Application\DTOs\CreatePartnerDTO;
use App\Core\Auth\Domain\Models\Employee;
use App\Models\Partner;
use Illuminate\Support\Facades\DB;

/**
 * Use Case: Register an employee as a Growth partner.
 */
final class ApplyAsPartner
{
    public function execute(Employee $employee, CreatePartnerDTO $dto): Partner
    {
        return DB::transaction(function () use ($employee, $dto): Partner {
            /** @var Partner $partner */
            $partner = Partner::create([
                'employee_id'  => $employee->id,
                'company_id'   => $employee->company_id,
                'name'         => $dto->name,
                'email'        => $dto->email,
                'phone'        => $dto->phone,
                'website'      => $dto->website,
                'commission_rate' => $dto->commissionRate ?? 0.10,
                'status'       => 'pending',
            ]);

            return $partner;
        });
    }
}
