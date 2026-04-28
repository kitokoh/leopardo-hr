<?php

namespace App\DTOs;

use App\Http\Requests\Api\V1\StoreEmployeeRequest;

final readonly class CreateEmployeeDTO
{
    /**
     * @param array<string, mixed> $extra_data
     */
    public function __construct(
        public string $first_name,
        public string $last_name,
        public string $email,
        public ?string $middle_name = null,
        public ?string $preferred_name = null,
        public ?string $phone = null,
        public ?string $personal_email = null,
        public string $role = 'employee',
        public ?string $manager_role = null,
        public ?string $password = null,
        public bool $send_invitation = false,
        public ?string $matricule = null,
        public ?string $company_id = null,
        public ?string $manager_id = null,
        public ?string $status = 'active',
        public ?string $address_line = null,
        public ?string $postal_code = null,
        public ?string $emergency_contact_name = null,
        public ?string $emergency_contact_phone = null,
        public ?string $emergency_contact_relation = null,
        public ?string $date_of_birth = null,
        public ?string $place_of_birth = null,
        public ?string $gender = null,
        public ?string $nationality = null,
        public ?string $marital_status = null,
        public ?string $contract_type = null,
        public ?string $contract_start = null,
        public string $salary_type = 'fixed',
        public float $salary_base = 0.0,
        public ?float $hourly_rate = null,
        public bool $biometric_face_enabled = false,
        public bool $biometric_fingerprint_enabled = false,
        public ?string $biometric_face_reference_path = null,
        public ?string $biometric_fingerprint_reference_path = null,
        public ?string $photo_path = null,
        public ?string $zkteco_id = null,
        public array $extra_data = [],
    ) {}

    public static function fromRequest(StoreEmployeeRequest $request): self
    {
        return new self(...$request->validated());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'middle_name' => $this->middle_name,
            'preferred_name' => $this->preferred_name,
            'phone' => $this->phone,
            'personal_email' => $this->personal_email,
            'role' => $this->role,
            'manager_role' => $this->manager_role,
            'send_invitation' => $this->send_invitation,
            'matricule' => $this->matricule,
            'company_id' => $this->company_id,
            'manager_id' => $this->manager_id,
            'status' => $this->status,
            'address_line' => $this->address_line,
            'postal_code' => $this->postal_code,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'emergency_contact_relation' => $this->emergency_contact_relation,
            'date_of_birth' => $this->date_of_birth,
            'place_of_birth' => $this->place_of_birth,
            'gender' => $this->gender,
            'nationality' => $this->nationality,
            'marital_status' => $this->marital_status,
            'contract_type' => $this->contract_type,
            'contract_start' => $this->contract_start,
            'salary_type' => $this->salary_type,
            'salary_base' => $this->salary_base,
            'hourly_rate' => $this->hourly_rate,
            'biometric_face_enabled' => $this->biometric_face_enabled,
            'biometric_fingerprint_enabled' => $this->biometric_fingerprint_enabled,
            'biometric_face_reference_path' => $this->biometric_face_reference_path,
            'biometric_fingerprint_reference_path' => $this->biometric_fingerprint_reference_path,
            'photo_path' => $this->photo_path,
            'zkteco_id' => $this->zkteco_id,
            'extra_data' => $this->extra_data,
        ];
    }
}
