<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Employee;
use App\Models\Language;
use App\Services\FeatureFlag;
use App\Services\MobileExperienceService;
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
            'salary_type' => $this->employeeAttribute('salary_type'),
            'salary_base' => $this->employeeAttribute('salary_base'),
            'hourly_rate' => $this->employeeAttribute('hourly_rate'),
            'currency' => $company?->currency,
            'biometric_face_enabled' => (bool) ($this->employeeAttribute('biometric_face_enabled') ?? false),
            'biometric_fingerprint_enabled' => (bool) ($this->employeeAttribute('biometric_fingerprint_enabled') ?? false),
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
            'company' => $company ? [
                'id' => $company->id,
                'name' => $company->name,
                'language' => $company->language,
                'timezone' => $company->timezone,
                'currency' => $company->currency,
            ] : null,
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
