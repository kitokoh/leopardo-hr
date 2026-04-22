<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ChangePasswordRequest;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Models\Employee;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            email: $request->validated('email'),
            password: $request->validated('password'),
            deviceName: $request->validated('device_name')
        );

        $employee = $result['employee'];

        return new JsonResponse([
            'data' => $this->serializeEmployee($employee),
            'token' => $result['token'],
            'token_type' => $result['token_type'],
            'token_expires_at' => $result['token_expires_at'],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        return new JsonResponse([
            'data' => $this->serializeEmployee($employee),
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $employee->fill($request->validated());
        $employee->save();

        return new JsonResponse([
            'data' => $this->serializeEmployee($employee->fresh()),
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        if (! Hash::check($request->validated('current_password'), $employee->password_hash)) {
            return new JsonResponse([
                'message' => 'Le mot de passe actuel est incorrect.',
                'error' => 'INVALID_CURRENT_PASSWORD',
            ], 422);
        }

        $employee->password_hash = Hash::make($request->validated('new_password'));
        $employee->save();

        return new JsonResponse([
            'status' => 'ok',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        return new JsonResponse(['status' => 'ok']);
    }

    private function serializeEmployee(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'company_id' => $employee->company_id,
            'first_name' => $employee->first_name,
            'middle_name' => $employee->middle_name,
            'last_name' => $employee->last_name,
            'preferred_name' => $employee->preferred_name,
            'email' => $employee->email,
            'personal_email' => $employee->personal_email,
            'phone' => $employee->phone,
            'role' => $employee->role,
            'manager_role' => $employee->manager_role,
            'status' => $employee->status,
            'photo_path' => $employee->photo_path,
            'biometric_face_enabled' => $employee->biometric_face_enabled,
            'biometric_fingerprint_enabled' => $employee->biometric_fingerprint_enabled,
            'address_line' => $employee->address_line,
            'postal_code' => $employee->postal_code,
            'emergency_contact_name' => $employee->emergency_contact_name,
            'emergency_contact_phone' => $employee->emergency_contact_phone,
            'extra_data' => $employee->extra_data ?? [],
            'capabilities' => $this->capabilitiesFor($employee),
            'suggested_home_route' => $employee->homeRoute(),
        ];
    }

    /**
     * Retourne le set de capacites actives pour l'employe (utilisable cote mobile
     * pour afficher / cacher des fonctionnalites sans redupliquer la logique RBAC).
     */
    private function capabilitiesFor(Employee $employee): array
    {
        return [
            'can_view_dashboard' => $employee->isManager(),
            'can_create_employees' => $employee->hasManagerRole('principal', 'rh'),
            'can_manage_invitations' => $employee->hasManagerRole('principal', 'rh'),
            'can_manage_biometrics' => $employee->hasManagerRole('principal', 'superviseur'),
            'can_view_payroll' => $employee->hasManagerRole('principal', 'comptable'),
            'is_principal' => $employee->hasManagerRole('principal'),
        ];
    }
}
