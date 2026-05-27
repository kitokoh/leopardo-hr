<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\ZktecoDevice;
use App\Services\ZktecoIntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Requests\Api\V1\Kiosk\StoreZktecoRequest;
use App\Http\Requests\Api\V1\Kiosk\SyncAttendanceZktecoRequest;
use App\Http\Requests\Api\V1\Kiosk\UpdateZktecoRequest;

class ZktecoController extends Controller
{
    public function __construct(
        private readonly ZktecoIntegrationService $zktecoService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless($actor->isManager(), 403, 'FORBIDDEN');

        $company = currentCompany();

        $devices = ZktecoDevice::query()
            ->where('company_id', $company->id)
            ->orderBy('name')
            ->get();

        return new JsonResponse(['data' => $devices]);
    }

    public function store(StoreZktecoRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless($actor->isManager(), 403, 'FORBIDDEN');

        $validated = $request->validated();

        $company = currentCompany();
        $device = $this->zktecoService->registerDevice($company->id, $validated);

        return new JsonResponse(['data' => $device], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless($actor->isManager(), 403, 'FORBIDDEN');

        $company = currentCompany();
        $device = ZktecoDevice::query()
            ->where('company_id', $company->id)
            ->findOrFail($id);

        $syncHistory = $this->zktecoService->getSyncHistory($device, 10);

        return new JsonResponse([
            'data' => $device,
            'sync_history' => $syncHistory,
        ]);
    }

    public function update(UpdateZktecoRequest $request, int $id): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless($actor->isManager(), 403, 'FORBIDDEN');

        $company = currentCompany();
        $device = ZktecoDevice::query()
            ->where('company_id', $company->id)
            ->findOrFail($id);

        $validated = $request->validated();

        $device->update($validated);

        return new JsonResponse(['data' => $device->fresh()]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless($actor->isManager(), 403, 'FORBIDDEN');

        $company = currentCompany();
        $device = ZktecoDevice::query()
            ->where('company_id', $company->id)
            ->findOrFail($id);

        $device->delete();

        return new JsonResponse(null, 204);
    }

    public function heartbeat(Request $request, string $serialNumber): JsonResponse
    {
        $device = ZktecoDevice::query()
            ->where('serial_number', $serialNumber)
            ->firstOrFail();

        $this->zktecoService->heartbeat($device);

        return new JsonResponse([
            'data' => [
                'device_id' => $device->id,
                'status' => 'online',
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }

    public function syncAttendance(SyncAttendanceZktecoRequest $request, string $serialNumber): JsonResponse
    {
        $validated = $request->validated();

        $device = ZktecoDevice::query()
            ->where('serial_number', $serialNumber)
            ->firstOrFail();

        $syncLog = $this->zktecoService->pullAttendance($device, $validated['records']);

        return new JsonResponse([
            'data' => [
                'sync_id' => $syncLog->id,
                'records_processed' => $syncLog->records_count,
                'errors' => $syncLog->errors_count,
                'status' => $syncLog->status,
            ],
        ], 201);
    }

    public function pushUsers(Request $request, string $serialNumber): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless($actor->isManager(), 403, 'FORBIDDEN');

        $device = ZktecoDevice::query()
            ->where('serial_number', $serialNumber)
            ->firstOrFail();

        $syncLog = $this->zktecoService->pushUsers($device);

        return new JsonResponse([
            'data' => [
                'sync_id' => $syncLog->id,
                'users_count' => $syncLog->records_count,
                'status' => $syncLog->status,
            ],
        ]);
    }

    public function syncLogs(Request $request, int $id): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless($actor->isManager(), 403, 'FORBIDDEN');

        $company = currentCompany();
        $device = ZktecoDevice::query()
            ->where('company_id', $company->id)
            ->findOrFail($id);

        $logs = $this->zktecoService->getSyncHistory($device, $request->integer('limit', 20));

        return new JsonResponse(['data' => $logs]);
    }
}
