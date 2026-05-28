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
        $resolvedLanguage = strtolower($this->preferred_language ?? $this->company?->language ?? Language::DEFAULT);
        $company = $this->company;
        $photoPath = data_get($this->resource, 'photo_path');
        $contractStart = data_get($this->resource, 'contract_start');

        return [
            'id' => $this->id,
            'matricule' => $this->matricule,
            'company_id' => $this->company_id,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'preferred_name' => $this->preferred_name,
            'email' => $this->email,
            'personal_email' => $this->personal_email,
            'recovery_email' => $this->recovery_email,
            'personal_phone' => $this->personal_phone,
            'phone' => $this->phone,
            'schedule_id' => $this->schedule_id,
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
            'salary_type' => $this->salary_type,
            'salary_base' => $this->salary_base,
            'hourly_rate' => $this->hourly_rate,
            'currency' => $company?->currency,
            'biometric_face_enabled' => $this->biometric_face_enabled,
            'biometric_fingerprint_enabled' => $this->biometric_fingerprint_enabled,
            'address_line' => $this->address_line,
            'postal_code' => $this->postal_code,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'extra_data' => $this->extra_data ?? [],
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
