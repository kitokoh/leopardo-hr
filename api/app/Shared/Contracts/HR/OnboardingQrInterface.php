<?php

declare(strict_types=1);

namespace App\Shared\Contracts\HR;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;

/**
 * Contract for signing/verifying the QR payloads used by the onboarding
 * flow (employee self-profile QR, company invite QR).
 *
 * Historiquement dans `HR\Domain\Contracts` (PA2-ARCH-003 — HR exposait ce
 * contrat pour qu'`OnboardingQrController` dépende de l'interface au lieu
 * d'importer `Onboarding\Infrastructure\Services\OnboardingQrService`).
 * Déplacé dans `Shared/Contracts` pour BOS-023 cycle 3 (#8211) : les
 * consommateurs cross-module — `KioskController` (Attendance) et
 * `OnboardingQrController` (HR) — ne dépendent que de ce contrat partagé,
 * sans import `Modules/X -> Modules/HR`. Toujours implémenté par
 * `OnboardingQrService` et bindé par OnboardingServiceProvider
 * (composition root décentralisée) — aucun changement de comportement.
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
