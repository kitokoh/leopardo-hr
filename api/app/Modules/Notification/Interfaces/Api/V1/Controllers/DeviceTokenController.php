<?php

declare(strict_types=1);

namespace App\Modules\Notification\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Notification\Domain\Models\DeviceToken;
use App\Modules\Notification\Infrastructure\Services\CommunicationService;
use App\Modules\Notification\Infrastructure\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function __construct(
        private readonly PushNotificationService $pushService,
        private readonly CommunicationService $communicationService,
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
            ->paginate($request->integer('per_page', 25));

        // Pagination (#1703) : `data` reste une liste simple (contrat
        // historique des clients), les métadonnées de page sont exposées
        // dans `meta` — un paginator brut dans `data` cassait
        // `assertJsonCount(1, 'data')` et les clients (13 clés imbriquées).
        return new JsonResponse([
            'data' => $tokens->items(),
            'meta' => [
                'current_page' => $tokens->currentPage(),
                'per_page' => $tokens->perPage(),
                'total' => $tokens->total(),
                'last_page' => $tokens->lastPage(),
            ],
        ]);
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
