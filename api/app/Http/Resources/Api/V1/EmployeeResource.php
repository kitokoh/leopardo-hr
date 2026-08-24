<?php

namespace App\Http\Resources\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Modules\HR\Application\Services\EmployeeDocumentService;
use App\Modules\HR\Infrastructure\Services\MobileExperienceService;
use App\Modules\HR\Infrastructure\Services\RoleInvitationService;
use App\Shared\Models\Language;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Employee
 */
class EmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Employee $employee */
        $employee = $this->resource;
        $resolvedLanguage = strtolower($this->employeeAttribute('preferred_language') ?? $this->company?->language ?? Language::DEFAULT);
        $company = $this->company;
        $photoPath = $this->employeeAttribute('photo_path');
        $contractStart = $this->employeeAttribute('contract_start');

        // Issue #5262 — RBAC fine-grained : les données salariales ne sont
        // exposées qu'aux rôles autorisés (principal/rh/comptable), au
        // manager d'équipe scopé (dept/superviseur) pour ses collaborateurs,
        // et à l'employé pour SON propre dossier. Masquage défensif : tout
        // autre contexte (fuite cross-rôle, contexte public) reçoit null.
        $viewer = $request->user();
        $canViewSalary = $viewer instanceof Employee
            && (
                (int) $viewer->id === (int) $employee->id
                || $viewer->hasManagerRole('principal', 'rh', 'comptable')
                || ($viewer->isTeamScoped() && $viewer->managesTeamMemberOf($employee))
            );

        return [
            'id' => $this->id,
            'matricule' => $this->employeeAttribute('matricule'),
            'company_id' => $this->company_id,
            'first_name' => $this->first_name,
            'middle_name' => $this->employeeAttribute('middle_name'),
            'last_name' => $this->last_name,
            'preferred_name' => $this->employeeAttribute('preferred_name'),
            'email' => $this->email,
            'personal_email' => $this->employeeAttribute('personal_email'),
            'recovery_email' => $this->employeeAttribute('recovery_email'),
            'personal_phone' => $this->employeeAttribute('personal_phone'),
            'phone' => $this->employeeAttribute('phone'),
            'schedule_id' => $this->employeeAttribute('schedule_id'),
            'schedule' => $this->schedule ? [
                'id' => $this->schedule->id,
                'name' => $this->schedule->name,
                'start_time' => $this->schedule->start_time,
                'end_time' => $this->schedule->end_time,
                'break_minutes' => $this->schedule->break_minutes,
                'late_tolerance_minutes' => $this->schedule->late_tolerance_minutes,
            ] : null,
            'role' => $this->role,
            'manager_role' => $this->manager_role,
            'status' => $this->status,
            'work_state' => $this->work_state,
            'work_state_label' => $this->work_state_label,
            'photo_path' => $photoPath,
            'photo_url' => $photoPath,
            'hire_date' => $contractStart instanceof DateTimeInterface ? $contractStart->format('Y-m-d') : null,
            'salary_type' => $canViewSalary ? $this->employeeAttribute('salary_type') : null,
            'salary_base' => $canViewSalary ? $this->employeeAttribute('salary_base') : null,
            'hourly_rate' => $canViewSalary ? $this->employeeAttribute('hourly_rate') : null,
            'currency' => $company?->currency,
            'biometric_face_enabled' => (bool) ($this->employeeAttribute('biometric_face_enabled') ?? false),
            'biometric_fingerprint_enabled' => (bool) ($this->employeeAttribute('biometric_fingerprint_enabled') ?? false),
            // #5122 — badge/carte de pointage (exposé seulement si renseigné)
            'badge_number' => $this->employeeAttribute('badge_number') ?: null,
            // #5122 — statut d'enrôlement par méthode (fingerprint / face / card)
            'enrollment' => [
                'fingerprint' => (bool) ($this->employeeAttribute('biometric_fingerprint_enabled') ?? false),
                'face' => (bool) ($this->employeeAttribute('biometric_face_enabled') ?? false),
                'card' => ! empty($this->employeeAttribute('badge_number')),
            ],
            'address_line' => $this->employeeAttribute('address_line'),
            'postal_code' => $this->employeeAttribute('postal_code'),
            'emergency_contact_name' => $this->employeeAttribute('emergency_contact_name'),
            'emergency_contact_phone' => $this->employeeAttribute('emergency_contact_phone'),
            'extra_data' => $this->employeeAttribute('extra_data') ?? [],
            'language' => $resolvedLanguage,
            'is_rtl' => Language::isRtl($resolvedLanguage),
            'capabilities' => $this->capabilities(),
            'features' => FeatureFlag::for($company),
            'mobile_experience' => app(MobileExperienceService::class)->for($employee),
            'suggested_home_route' => $this->homeRoute(),
            'app_links' => RoleInvitationService::getAppDownloadLink(
                $this->role,
                $this->manager_role ?? 'employee'
            ),
            'company' => $company ? [
                'id' => $company->id,
                'name' => $company->name,
                'language' => $company->language,
                'timezone' => $company->timezone,
                'currency' => $company->currency,
            ] : null,
            // #5326 (G3) — badge « dossier complet » sur la fiche employé.
            // Présent uniquement quand la relation est chargée (show), jamais
            // sur les listes (évite un N+1 sur index).
            'documents_status' => $this->whenLoaded('employeeDocuments', function (): array {
                // whenLoaded garantit que la relation est chargée (non nulle) ici.
                /** @var \Illuminate\Support\Collection<int, \App\Modules\HR\Domain\Models\EmployeeDocument> $documents */
                $documents = $this->employeeDocuments;

                return EmployeeDocumentService::dossierSummary((string) $this->status, $documents);
            }),
        ];
    }

    private function employeeAttribute(string $key): mixed
    {
        /** @var Employee $employee */
        $employee = $this->resource;

        if (! array_key_exists($key, $employee->getAttributes())) {
            return null;
        }

        return $employee->getAttributeValue($key);
    }

    private function capabilities(): array
    {
        return [
            'can_view_dashboard' => $this->isManager(),
            'can_create_employees' => $this->hasManagerRole('principal', 'rh'),
            'can_manage_invitations' => $this->hasManagerRole('principal', 'rh'),
            'can_manage_biometrics' => $this->hasManagerRole('principal', 'superviseur'),
            'can_view_payroll' => $this->hasManagerRole('principal', 'comptable'),
            'is_principal' => $this->hasManagerRole('principal'),
        ];
    }
}
