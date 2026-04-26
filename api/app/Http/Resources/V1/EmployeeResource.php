<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'matricule' => $this->matricule,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'preferred_name' => $this->preferred_name,
            'email' => $this->email,
            'personal_email' => $this->personal_email,
            'phone' => $this->phone,
            'address_line' => $this->address_line,
            'postal_code' => $this->postal_code,
            'gender' => $this->gender,
            'nationality' => $this->nationality,
            'marital_status' => $this->marital_status,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'place_of_birth' => $this->place_of_birth,
            'role' => $this->role,
            'manager_role' => $this->manager_role,
            'status' => $this->status,
            'contract_type' => $this->contract_type,
            'contract_start' => $this->contract_start?->toDateString(),
            'contract_end' => $this->contract_end?->toDateString(),
            'salary_type' => $this->salary_type,
            'salary_base' => (float) $this->salary_base,
            'hourly_rate' => (float) $this->hourly_rate,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'emergency_contact_relation' => $this->emergency_contact_relation,
            'biometric_face_enabled' => (bool) $this->biometric_face_enabled,
            'biometric_fingerprint_enabled' => (bool) $this->biometric_fingerprint_enabled,
            'extra_data' => $this->extra_data ?? [],
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
