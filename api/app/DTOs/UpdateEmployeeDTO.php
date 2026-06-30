<?php

namespace App\DTOs;

use App\Http\Requests\Api\V1\UpdateEmployeeRequest;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use Illuminate\Http\Request;

final readonly class UpdateEmployeeDTO
{
    /** @param array<string, mixed>|null $extra_data */
    public function __construct(
        public ?string $first_name = null,
        public ?string $last_name = null,
        public ?string $email = null,
        public ?string $middle_name = null,
        public ?string $preferred_name = null,
        public ?string $phone = null,
        public ?string $personal_email = null,
        public ?string $recovery_email = null,
        public ?string $personal_phone = null,
        public ?string $role = null,
        public ?string $manager_role = null,
        public ?string $password = null,
        public ?string $status = null,
        public ?string $manager_id = null,
        public ?string $contract_start = null,
        public ?int $schedule_id = null,
        public ?string $salary_type = null,
        public int|float|null $salary_base = null,
        public int|float|null $hourly_rate = null,
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
        public ?string $photo_path = null,
        public ?string $zkteco_id = null,
        public ?bool $biometric_face_enabled = null,
        public ?bool $biometric_fingerprint_enabled = null,
        public ?string $biometric_face_reference_path = null,
        public ?string $biometric_fingerprint_reference_path = null,
        public ?array $extra_data = null,
    ) {}

    public static function fromRequest(UpdateEmployeeRequest|UpdateProfileRequest|Request $request): self
    {
        /** @var array<string, mixed> $validated */
        $validated = method_exists($request, 'validated') ? $request->validated() : $request->all();

        return new self(
            first_name:                           isset($validated['first_name']) ? (string) $validated['first_name'] : null,
            last_name:                            isset($validated['last_name']) ? (string) $validated['last_name'] : null,
            email:                                isset($validated['email']) ? (string) $validated['email'] : null,
            middle_name:                          isset($validated['middle_name']) ? (string) $validated['middle_name'] : null,
            preferred_name:                       isset($validated['preferred_name']) ? (string) $validated['preferred_name'] : null,
            phone:                                isset($validated['phone']) ? (string) $validated['phone'] : null,
            personal_email:                       isset($validated['personal_email']) ? (string) $validated['personal_email'] : null,
            recovery_email:                       isset($validated['recovery_email']) ? (string) $validated['recovery_email'] : null,
            personal_phone:                       isset($validated['personal_phone']) ? (string) $validated['personal_phone'] : null,
            role:                                 isset($validated['role']) ? (string) $validated['role'] : null,
            manager_role:                         isset($validated['manager_role']) ? (string) $validated['manager_role'] : null,
            password:                             isset($validated['password']) ? (string) $validated['password'] : null,
            status:                               isset($validated['status']) ? (string) $validated['status'] : null,
            manager_id:                           isset($validated['manager_id']) ? (string) $validated['manager_id'] : null,
            contract_start:                       isset($validated['contract_start']) ? (string) $validated['contract_start'] : null,
            schedule_id:                          isset($validated['schedule_id']) ? (int) $validated['schedule_id'] : null,
            salary_type:                          isset($validated['salary_type']) ? (string) $validated['salary_type'] : null,
            salary_base:                          isset($validated['salary_base']) ? (float) $validated['salary_base'] : null,
            hourly_rate:                          isset($validated['hourly_rate']) ? (float) $validated['hourly_rate'] : null,
            address_line:                         isset($validated['address_line']) ? (string) $validated['address_line'] : null,
            postal_code:                          isset($validated['postal_code']) ? (string) $validated['postal_code'] : null,
            emergency_contact_name:               isset($validated['emergency_contact_name']) ? (string) $validated['emergency_contact_name'] : null,
            emergency_contact_phone:              isset($validated['emergency_contact_phone']) ? (string) $validated['emergency_contact_phone'] : null,
            emergency_contact_relation:           isset($validated['emergency_contact_relation']) ? (string) $validated['emergency_contact_relation'] : null,
            date_of_birth:                        isset($validated['date_of_birth']) ? (string) $validated['date_of_birth'] : null,
            place_of_birth:                       isset($validated['place_of_birth']) ? (string) $validated['place_of_birth'] : null,
            gender:                               isset($validated['gender']) ? (string) $validated['gender'] : null,
            nationality:                          isset($validated['nationality']) ? (string) $validated['nationality'] : null,
            marital_status:                       isset($validated['marital_status']) ? (string) $validated['marital_status'] : null,
            photo_path:                           isset($validated['photo_path']) ? (string) $validated['photo_path'] : null,
            zkteco_id:                            isset($validated['zkteco_id']) ? (string) $validated['zkteco_id'] : null,
            biometric_face_enabled:               isset($validated['biometric_face_enabled']) ? (bool) $validated['biometric_face_enabled'] : null,
            biometric_fingerprint_enabled:        isset($validated['biometric_fingerprint_enabled']) ? (bool) $validated['biometric_fingerprint_enabled'] : null,
            biometric_face_reference_path:        isset($validated['biometric_face_reference_path']) ? (string) $validated['biometric_face_reference_path'] : null,
            biometric_fingerprint_reference_path: isset($validated['biometric_fingerprint_reference_path']) ? (string) $validated['biometric_fingerprint_reference_path'] : null,
            extra_data:                           is_array($validated['extra_data'] ?? null) ? $validated['extra_data'] : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'first_name'                           => $this->first_name,
            'last_name'                            => $this->last_name,
            'email'                                => $this->email,
            'middle_name'                          => $this->middle_name,
            'preferred_name'                       => $this->preferred_name,
            'phone'                                => $this->phone,
            'personal_email'                       => $this->personal_email,
            'recovery_email'                       => $this->recovery_email,
            'personal_phone'                       => $this->personal_phone,
            'role'                                 => $this->role,
            'manager_role'                         => $this->manager_role,
            'password'                             => $this->password,
            'status'                               => $this->status,
            'manager_id'                           => $this->manager_id,
            'contract_start'                       => $this->contract_start,
            'schedule_id'                          => $this->schedule_id,
            'salary_type'                          => $this->salary_type,
            'salary_base'                          => $this->salary_base,
            'hourly_rate'                          => $this->hourly_rate,
            'address_line'                         => $this->address_line,
            'postal_code'                          => $this->postal_code,
            'emergency_contact_name'               => $this->emergency_contact_name,
            'emergency_contact_phone'              => $this->emergency_contact_phone,
            'emergency_contact_relation'           => $this->emergency_contact_relation,
            'date_of_birth'                        => $this->date_of_birth,
            'place_of_birth'                       => $this->place_of_birth,
            'gender'                               => $this->gender,
            'nationality'                          => $this->nationality,
            'marital_status'                       => $this->marital_status,
            'photo_path'                           => $this->photo_path,
            'zkteco_id'                            => $this->zkteco_id,
            'biometric_face_enabled'               => $this->biometric_face_enabled,
            'biometric_fingerprint_enabled'        => $this->biometric_fingerprint_enabled,
            'biometric_face_reference_path'        => $this->biometric_face_reference_path,
            'biometric_fingerprint_reference_path' => $this->biometric_fingerprint_reference_path,
            'extra_data'                           => $this->extra_data,
        ], fn (mixed $v): bool => $v !== null);
    }
}
