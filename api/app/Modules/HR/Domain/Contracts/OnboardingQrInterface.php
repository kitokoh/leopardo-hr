<?php

declare(strict_types=1);

namespace App\Modules\HR\Domain\Contracts;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;

/**
 * Contract for signing/verifying the QR payloads used by the onboarding
 * flow (employee self-profile QR, company invite QR). Owned by HR so
 * OnboardingQrController depends on this interface instead of importing
 * App\Modules\Onboarding\Infrastructure\Services\OnboardingQrService
 * directly (PA2-ARCH-003). Bound to the existing Onboarding implementation
 * in HRServiceProvider.
 */
interface OnboardingQrInterface
{
    /**
     * @return array<string, mixed>
     */
    public function employeeProfilePayload(Employee $employee): array;

    /**
     * @return array<string, mixed>
     */
    public function companyOnboardingPayload(Company $company, Employee $actor): array;

    /**
     * @return array<string, mixed>
     */
    public function decodeEmployeeProfile(string $token): array;

    /**
     * @return array<string, mixed>
     */
    public function decodeCompanyOnboarding(string $token): array;
}
