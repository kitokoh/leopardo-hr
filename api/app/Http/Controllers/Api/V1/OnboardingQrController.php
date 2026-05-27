<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\CreateEmployeeDTO;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EmployeeResource;
use App\Models\Company;
use App\Models\CompanyRequest;
use App\Models\Employee;
use App\Models\User;
use App\Rules\GlobalEmailUnique;
use App\Services\EmployeeService;
use App\Services\OnboardingQrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class OnboardingQrController extends Controller
{
    public function __construct(
        private readonly OnboardingQrService $qrService,
        private readonly EmployeeService $employeeService,
    ) {}

    public function employeeProfile(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        return new JsonResponse([
            'data' => $this->qrService->employeeProfilePayload($employee),
        ]);
    }

    public function companyOnboarding(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless($actor->hasManagerRole('principal', 'rh'), 403);

        $company = $this->resolveCurrentCompany($actor);

        return new JsonResponse([
            'data' => $this->qrService->companyOnboardingPayload($company, $actor),
        ]);
    }

    public function scanEmployee(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless($actor->hasManagerRole('principal', 'rh'), 403);

        $validated = $request->validate([
            'qr_token' => ['required', 'string', 'max:5000'],
        ]);

        $payload = $this->qrService->decodeEmployeeProfile((string) $validated['qr_token']);
        /** @var array<string, mixed> $employee */
        $employee = is_array($payload['employee'] ?? null) ? $payload['employee'] : [];

        return new JsonResponse([
            'data' => [
                'prefill' => [
                    'first_name' => (string) ($employee['first_name'] ?? ''),
                    'last_name' => (string) ($employee['last_name'] ?? ''),
                    'preferred_name' => $employee['preferred_name'] ?? null,
                    'email' => (string) ($employee['email'] ?? ''),
                    'phone' => $employee['phone'] ?? $employee['personal_phone'] ?? null,
                    'personal_email' => $employee['personal_email'] ?? null,
                    'source' => 'employee_qr',
                ],
            ],
        ]);
    }

    public function scanCompany(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $validated = $request->validate([
            'qr_token' => ['required', 'string', 'max:5000'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $payload = $this->qrService->decodeCompanyOnboarding((string) $validated['qr_token']);
        /** @var array<string, mixed> $companyPayload */
        $companyPayload = is_array($payload['company'] ?? null) ? $payload['company'] : [];

        $targetCompanyId = (string) ($companyPayload['id'] ?? '');
        $targetCompany = $this->findCompanyFromPublicSchema($targetCompanyId);
        $user = $this->resolveUserFromEmployee($employee);

        $existing = CompanyRequest::query()
            ->where('user_id', $user->id)
            ->where('approved_company_id', $targetCompany->id)
            ->where('status', 'pending')
            ->first();

        if ($existing instanceof CompanyRequest) {
            return new JsonResponse([
                'data' => [
                    'id' => $existing->id,
                    'status' => $existing->status,
                    'company_name' => $existing->company_name,
                    'created_at' => $existing->created_at?->toIso8601String(),
                ],
                'message' => 'COMPANY_JOIN_REQUEST_ALREADY_PENDING',
            ]);
        }

        $requestModel = $user->companyRequests()->create([
            'employee_id' => $employee->id,
            'company_name' => $targetCompany->name,
            'sector' => $targetCompany->sector ?? 'RH',
            'country' => $targetCompany->country ?? 'DZ',
            'city' => $targetCompany->city ?? '',
            'email' => $employee->email,
            'phone' => $employee->personal_phone ?? $employee->phone,
            'description' => trim((string) ($validated['message'] ?? 'Demande d integration via QR entreprise.')),
            'manager_name' => trim($employee->first_name.' '.$employee->last_name),
            'manager_phone' => $employee->personal_phone ?? $employee->phone,
            'notes' => 'QR onboarding company_id='.$targetCompany->id,
            'approved_company_id' => $targetCompany->id,
            'status' => 'pending',
        ]);

        return new JsonResponse([
            'data' => [
                'id' => $requestModel->id,
                'status' => $requestModel->status,
                'company_name' => $requestModel->company_name,
                'created_at' => $requestModel->created_at?->toIso8601String(),
            ],
            'message' => 'COMPANY_JOIN_REQUEST_CREATED',
        ], 201);
    }

    public function createEmployeeFromQr(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless($actor->hasManagerRole('principal', 'rh'), 403);

        $validated = $request->validate([
            'qr_token' => ['required', 'string', 'max:5000'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('employees', 'email'), new GlobalEmailUnique],
            'matricule' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('employees', 'matricule')->where(
                    fn ($query) => $query->where('company_id', $actor->company_id)
                ),
            ],
            'contract_start' => ['nullable', 'date_format:Y-m-d'],
            'schedule_id' => [
                'nullable',
                'integer',
                Rule::exists('schedules', 'id')->where(fn ($query) => $query->where('company_id', $actor->company_id)),
            ],
            'salary_type' => ['nullable', 'in:fixed,hourly,daily'],
            'salary_base' => ['nullable', 'numeric', 'min:0'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'extra_data' => ['nullable', 'array'],
            'extra_data.department' => ['nullable', 'string', 'max:120'],
            'extra_data.job_title' => ['nullable', 'string', 'max:120'],
            'extra_data.work_location' => ['nullable', 'string', 'max:120'],
            'send_invitation' => ['nullable', 'boolean'],
        ]);

        $payload = $this->qrService->decodeEmployeeProfile((string) $validated['qr_token']);
        /** @var array<string, mixed> $profile */
        $profile = is_array($payload['employee'] ?? null) ? $payload['employee'] : [];

        Validator::make($profile, [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
        ])->validate();

        $email = (string) ($validated['email'] ?? $profile['email'] ?? '');
        Validator::make(
            ['email' => $email],
            ['email' => ['required', 'email', 'max:150', Rule::unique('employees', 'email'), new GlobalEmailUnique]]
        )->validate();

        $employee = $this->employeeService->create(
            new CreateEmployeeDTO(
                first_name: (string) ($profile['first_name'] ?? ''),
                last_name: (string) ($profile['last_name'] ?? ''),
                email: $email,
                phone: $this->stringOrNull($profile['phone'] ?? $profile['personal_phone'] ?? null),
                personal_email: $this->stringOrNull($profile['personal_email'] ?? null),
                role: 'employee',
                send_invitation: (bool) ($validated['send_invitation'] ?? true),
                matricule: $this->stringOrNull($validated['matricule'] ?? null),
                schedule_id: isset($validated['schedule_id']) ? (int) $validated['schedule_id'] : null,
                contract_start: $this->stringOrNull($validated['contract_start'] ?? null),
                salary_type: (string) ($validated['salary_type'] ?? 'fixed'),
                salary_base: (float) ($validated['salary_base'] ?? 0.0),
                hourly_rate: isset($validated['hourly_rate']) ? (float) $validated['hourly_rate'] : null,
                extra_data: is_array($validated['extra_data'] ?? null) ? $validated['extra_data'] : [],
            ),
            $actor
        );

        return (new EmployeeResource($employee))
            ->response()
            ->setStatusCode(201);
    }

    private function resolveCurrentCompany(Employee $actor): Company
    {
        return $this->findCompanyFromPublicSchema((string) $actor->company_id);
    }

    private function findCompanyFromPublicSchema(string $companyId): Company
    {
        if (DB::getDriverName() !== 'pgsql') {
            return Company::query()->findOrFail($companyId);
        }

        $previous = DB::selectOne('SHOW search_path')->search_path ?? 'shared_tenants,public';
        DB::statement('SET search_path TO public');

        try {
            return Company::query()->findOrFail($companyId);
        } finally {
            DB::statement('SET search_path TO '.$previous);
        }
    }

    private function resolveUserFromEmployee(Employee $employee): User
    {
        return User::firstOrCreate(
            ['email' => $employee->email],
            [
                'first_name' => $employee->first_name ?: 'Employe',
                'last_name' => $employee->last_name ?: 'Leopardo',
                'phone' => $employee->personal_phone ?? $employee->phone,
                'password_hash' => $employee->password_hash ?: Hash::make(str()->random(32)),
                'provider' => 'employee',
                'preferred_language' => $employee->preferred_language ?? 'fr',
                'status' => $employee->status ?? 'active',
            ]
        );
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
