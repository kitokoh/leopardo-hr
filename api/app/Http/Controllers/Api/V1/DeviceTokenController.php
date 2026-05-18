<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\Employee;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function __construct(
        private readonly PushNotificationService $pushService,
    ) {}

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['required', 'in:ios,android,web'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        /** @var Employee $user */
        $user = $request->user();

        $deviceToken = $this->pushService->registerToken(
            $user,
            $validated['token'],
            $validated['platform'],
            $validated['device_name'] ?? null
        );

        return new JsonResponse(['data' => $deviceToken], 201);
    }

    public function unregister(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        /** @var Employee $user */
        $user = $request->user();

        $this->pushService->removeToken($user, $validated['token']);

        return new JsonResponse(['message' => 'Device token removed.']);
    }

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $tokens = DeviceToken::query()
            ->where('employee_id', $user->id)
            ->where('is_active', true)
            ->orderByDesc('last_used_at')
            ->get();

        return new JsonResponse(['data' => $tokens]);
    }

    public function sendTest(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        abort_unless($user->isManager(), 403, 'FORBIDDEN');

        $validated = $request->validate([
            'employee_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:500'],
        ]);

        $company = currentCompany();
        $target = Employee::query()
            ->where('company_id', $company->id)
            ->where('id', $validated['employee_id'])
            ->firstOrFail();

        $sent = $this->pushService->sendToEmployee(
            $target,
            $validated['title'],
            $validated['body']
        );

        return new JsonResponse([
            'data' => [
                'sent' => $sent,
                'employee_id' => $target->id,
            ],
        ]);
    }
}
