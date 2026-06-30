<?php

namespace App\DTOs;

use App\Http\Requests\Api\V1\StoreEmployeeRequest;

final readonly class CreateEmployeeDTO
{
    /** @param array<string, mixed> $extra_data */
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
        public ?int $schedule_id = null,
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
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        return new self(
            first_name:                          (string) ($validated['first_name'] ?? ''),
            last_name:                           (string) ($validated['last_name'] ?? ''),
            email:                               (string) ($validated['email'] ?? ''),
            middle_name:                         isset($validated['middle_name']) ? (string) $validated['middle_name'] : null,
            preferred_name:                      isset($validated['preferred_name']) ? (string) $validated['preferred_name'] : null,
            phone:                               isset($validated['phone']) ? (string) $validated['phone'] : null,
            personal_email:                      isset($validated['personal_email']) ? (string) $validated['personal_email'] : null,
            role:                                isset($validated['role']) ? (string) $validated['role'] : 'employee',
            manager_role:                        isset($validated['manager_role']) ? (string) $validated['manager_role'] : null,
            password:                            isset($validated['password']) ? (string) $validated['password'] : null,
            send_invitation:                     (bool) ($validated['send_invitation'] ?? false),
            matricule:                           isset($validated['matricule']) ? (string) $validated['matricule'] : null,
            company_id:                          isset($validated['company_id']) ? (string) $validated['company_id'] : null,
            manager_id:                          isset($validated['manager_id']) ? (string) $validated['manager_id'] : null,
            schedule_id:                         isset($validated['schedule_id']) ? (int) $validated['schedule_id'] : null,
            status:                              isset($validated['status']) ? (string) $validated['status'] : 'active',
            address_line:                        isset($validated['address_line']) ? (string) $validated['address_line'] : null,
            postal_code:                         isset($validated['postal_code']) ? (string) $validated['postal_code'] : null,
            emergency_contact_name:              isset($validated['emergency_contact_name']) ? (string) $validated['emergency_contact_name'] : null,
            emergency_contact_phone:             isset($validated['emergency_contact_phone']) ? (string) $validated['emergency_contact_phone'] : null,
            emergency_contact_relation:          isset($validated['emergency_contact_relation']) ? (string) $validated['emergency_contact_relation'] : null,
            date_of_birth:                       isset($validated['date_of_birth']) ? (string) $validated['date_of_birth'] : null,
            place_of_birth:                      isset($validated['place_of_birth']) ? (string) $validated['place_of_birth'] : null,
            gender:                              isset($validated['gender']) ? (string) $validated['gender'] : null,
            nationality:                         isset($validated['nationality']) ? (string) $validated['nationality'] : null,
            marital_status:                      isset($validated['marital_status']) ? (string) $validated['marital_status'] : null,
            contract_type:                       isset($validated['contract_type']) ? (string) $validated['contract_type'] : null,
            contract_start:                      isset($validated['contract_start']) ? (string) $validated['contract_start'] : null,
            salary_type:                         isset($validated['salary_type']) ? (string) $validated['salary_type'] : 'fixed',
            salary_base:                         (float) ($validated['salary_base'] ?? 0.0),
            hourly_rate:                         isset($validated['hourly_rate']) ? (float) $validated['hourly_rate'] : null,
            biometric_face_enabled:              (bool) ($validated['biometric_face_enabled'] ?? false),
            biometric_fingerprint_enabled:       (bool) ($validated['biometric_fingerprint_enabled'] ?? false),
            biometric_face_reference_path:       isset($validated['biometric_face_reference_path']) ? (string) $validated['biometric_face_reference_path'] : null,
            biometric_fingerprint_reference_path: isset($validated['biometric_fingerprint_reference_path']) ? (string) $validated['biometric_fingerprint_reference_path'] : null,
            photo_path:                          isset($validated['photo_path']) ? (string) $validated['photo_path'] : null,
            zkteco_id:                           isset($validated['zkteco_id']) ? (string) $validated['zkteco_id'] : null,
            extra_data:                          is_array($validated['extra_data'] ?? null) ? $validated['extra_data'] : [],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'first_name'                          => $this->first_name,
            'last_name'                           => $this->last_name,
            'email'                               => $this->email,
            'middle_name'                         => $this->middle_name,
            'preferred_name'                      => $this->preferred_name,
            'phone'                               => $this->phone,
            'personal_email'                      => $this->personal_email,
            'role'                                => $this->role,
            'manager_role'                        => $this->manager_role,
            'password'                            => $this->password,
            'send_invitation'                     => $this->send_invitation,
            'matricule'                           => $this->matricule,
            'company_id'                          => $this->company_id,
            'manager_id'                          => $this->manager_id,
            'schedule_id'                         => $this->schedule_id,
            'status'                              => $this->status,
            'address_line'                        => $this->address_line,
            'postal_code'                         => $this->postal_code,
            'emergency_contact_name'              => $this->emergency_contact_name,
            'emergency_contact_phone'             => $this->emergency_contact_phone,
            'emergency_contact_relation'          => $this->emergency_contact_relation,
            'date_of_birth'                       => $this->date_of_birth,
            'place_of_birth'                      => $this->place_of_birth,
            'gender'                              => $this->gender,
            'nationality'                         => $this->nationality,
            'marital_status'                      => $this->marital_status,
            'contract_type'                       => $this->contract_type,
            'contract_start'                      => $this->contract_start,
            'salary_type'                         => $this->salary_type,
            'salary_base'                         => $this->salary_base,
            'hourly_rate'                         => $this->hourly_rate,
            'biometric_face_enabled'              => $this->biometric_face_enabled,
            'biometric_fingerprint_enabled'       => $this->biometric_fingerprint_enabled,
            'biometric_face_reference_path'       => $this->biometric_face_reference_path,
            'biometric_fingerprint_reference_path' => $this->biometric_fingerprint_reference_path,
            'photo_path'                          => $this->photo_path,
            'zkteco_id'                           => $this->zkteco_id,
            'extra_data'                          => $this->extra_data,
        ], fn (mixed $v): bool => $v !== null);
    }
}
