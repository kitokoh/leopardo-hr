<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ChangePasswordRequest;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Language;
use App\Services\AuthService;
use App\Services\FeatureFlag;
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

        if ($this->resolveCompany($employee) === null) {
            throw new \App\Exceptions\CompanyNotFoundException();
        }

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

    public function updateLanguage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'language' => [
                'required',
                'string',
                'size:2',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || ! Language::isSupported($value)) {
                        $fail(__('validation.in', ['attribute' => $attribute]));
                    }
                },
            ],
        ]);

        /** @var Employee $employee */
        $employee = $request->user();
        $employee->preferred_language = strtolower($validated['language']);
        $employee->save();

        app()->setLocale($employee->preferred_language);

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
                'error' => 'INVALID_CURRENT_PASSWORD',
                'message' => 'INVALID_CURRENT_PASSWORD',
                'localized_message' => __('errors.INVALID_CURRENT_PASSWORD'),
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

        return new JsonResponse(['message' => 'LOGGED_OUT']);
    }

    private function serializeEmployee(Employee $employee): array
    {
        $company = $this->resolveCompany($employee);
        $resolvedLanguage = strtolower($employee->preferred_language ?? $employee->company?->language ?? Language::DEFAULT);

        return [
            'id' => $employee->id,
            'matricule' => $employee->matricule,
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
            'language' => $resolvedLanguage,
            'is_rtl' => Language::isRtl($resolvedLanguage),
            'capabilities' => $this->capabilitiesFor($employee),
            'features' => FeatureFlag::for($company),
            'suggested_home_route' => $employee->homeRoute(),
            'company' => $company ? [
                'id' => $company->id,
                'name' => $company->name,
                'language' => $company->language,
                'timezone' => $company->timezone,
                'currency' => $company->currency,
            ] : null,
        ];
    }

    private function resolveCompany(Employee $employee): ?Company
    {
        return $employee->company;
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
