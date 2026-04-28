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
        public ?string $role = null,
        public ?string $manager_role = null,
        public ?string $password = null,
        public ?string $status = null,
        public ?string $manager_id = null,
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
        $validated = method_exists($request, 'validated') ? $request->validated() : $request->all();

        return new self(...$validated);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'middle_name' => $this->middle_name,
            'preferred_name' => $this->preferred_name,
            'phone' => $this->phone,
            'personal_email' => $this->personal_email,
            'role' => $this->role,
            'manager_role' => $this->manager_role,
            'password' => $this->password,
            'status' => $this->status,
            'manager_id' => $this->manager_id,
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
            'photo_path' => $this->photo_path,
            'zkteco_id' => $this->zkteco_id,
            'biometric_face_enabled' => $this->biometric_face_enabled,
            'biometric_fingerprint_enabled' => $this->biometric_fingerprint_enabled,
            'biometric_face_reference_path' => $this->biometric_face_reference_path,
            'biometric_fingerprint_reference_path' => $this->biometric_fingerprint_reference_path,
            'extra_data' => $this->extra_data,
        ], fn ($value) => $value !== null);
    }
}
