<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Application\Actions;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduCampus;

/**
 * Amorçage minimal EduManager lors de l'activation de la solution (BOS-016, issue #8205).
 *
 * Crée un campus par défaut et une année académique par défaut si absents.
 * Idempotent : firstOrCreate sur les attributs clés du tenant.
 */
final class SeedMinimalEduManagerAction
{
    public function __construct(
        private readonly TenantManager $tenants,
    ) {}

    public function execute(Company $company): void
    {
        $this->tenants->withinTenant($company, function () use ($company): void {
            if (EduCampus::doesntExist()) {
                EduCampus::firstOrCreate(
                    [
                        'company_id' => $company->id,
                        'code' => 'MAIN',
                    ],
                    [
                        'name' => 'Campus principal',
                        'status' => EduCampus::STATUS_ACTIVE,
                        'timezone' => $company->timezone ?? 'UTC',
                    ]
                );
            }

            if (EduAcademicYear::doesntExist()) {
                EduAcademicYear::firstOrCreate(
                    [
                        'company_id' => $company->id,
                        'name' => '2026-2027',
                    ],
                    [
                        'start_date' => '2026-09-01',
                        'end_date' => '2027-06-30',
                        'status' => EduAcademicYear::STATUS_ACTIVE,
                    ]
                );
            }
        });
    }
}
