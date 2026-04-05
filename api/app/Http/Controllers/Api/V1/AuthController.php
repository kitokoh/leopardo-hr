<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            email: $request->validated('email'),
            password: $request->validated('password'),
            deviceName: $request->validated('device_name')
        );

        $employee = $result['employee'];

        return new JsonResponse([
            'data' => [
                'id' => $employee->id,
                'company_id' => $employee->company_id,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'email' => $employee->email,
                'role' => $employee->role,
                'status' => $employee->status,
            ],
            'token' => $result['token'],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var \App\Models\Employee $employee */
        $employee = $request->user();

        return new JsonResponse([
            'data' => [
                'id' => $employee->id,
                'company_id' => $employee->company_id,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'email' => $employee->email,
                'role' => $employee->role,
                'status' => $employee->status,
            ],
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
}

