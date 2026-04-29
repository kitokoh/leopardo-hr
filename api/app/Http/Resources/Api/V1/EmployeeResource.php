<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Language;
use App\Services\FeatureFlag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $resolvedLanguage = strtolower($this->preferred_language ?? $this->company?->language ?? Language::DEFAULT);
        $company = $this->company;

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
            'phone' => $this->phone,
            'role' => $this->role,
            'manager_role' => $this->manager_role,
            'status' => $this->status,
            'photo_path' => $this->photo_path,
            'photo_url' => $this->photo_path,
            'hire_date' => $this->contract_start?->format('Y-m-d'),
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
