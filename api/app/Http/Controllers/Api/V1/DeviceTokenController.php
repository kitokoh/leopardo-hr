<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\Employee;
use App\Services\Communication\CommunicationService;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Requests\Api\V1\Device\RegisterDeviceTokenRequest;
use App\Http\Requests\Api\V1\Device\SendTestDeviceTokenRequest;
use App\Http\Requests\Api\V1\Device\UnregisterDeviceTokenRequest;

class DeviceTokenController extends Controller
{
    public function __construct(
        private readonly PushNotificationService $pushService,
        private readonly CommunicationService $communicationService,
    ) {}

    public function register(RegisterDeviceTokenRequest $request): JsonResponse
    {
        $validated = $request->validated();

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

    public function unregister(UnregisterDeviceTokenRequest $request): JsonResponse
    {
        $validated = $request->validated();

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

    public function sendTest(SendTestDeviceTokenRequest $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        abort_unless($user->isManager(), 403, 'FORBIDDEN');

        $validated = $request->validated();

        $company = currentCompany();
        $target = Employee::query()
            ->where('company_id', $company->id)
            ->where('id', $validated['employee_id'])
            ->firstOrFail();

        $result = $this->communicationService->notifyEmployee($target, 'generic', [
            'category' => 'system',
            'title' => $validated['title'],
            'body' => $validated['body'],
            'source' => 'manager_push_test',
            'employee_id' => $target->id,
        ], ['app', 'push']);

        return new JsonResponse([
            'data' => [
                'sent' => ($result['results']['push'] ?? 'skipped') === 'sent' ? 1 : 0,
                'results' => $result['results'],
                'notification_id' => $result['notification_id'],
                'employee_id' => $target->id,
            ],
        ]);
    }
}
