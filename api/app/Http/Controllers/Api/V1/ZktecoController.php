<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\ZktecoDevice;
use App\Services\ZktecoIntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function store(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless($actor->isManager(), 403, 'FORBIDDEN');

        $validated = $request->validate([
            'serial_number' => ['required', 'string', 'max:100', 'unique:zkteco_devices,serial_number'],
            'name' => ['required', 'string', 'max:120'],
            'ip_address' => ['nullable', 'ip'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'protocol' => ['nullable', 'in:tcp,udp,cloud_api'],
            'location_label' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:60'],
            'firmware_version' => ['nullable', 'string', 'max:60'],
        ]);

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

    public function update(Request $request, int $id): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless($actor->isManager(), 403, 'FORBIDDEN');

        $company = currentCompany();
        $device = ZktecoDevice::query()
            ->where('company_id', $company->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'ip_address' => ['nullable', 'ip'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'protocol' => ['nullable', 'in:tcp,udp,cloud_api'],
            'location_label' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:online,offline,maintenance'],
        ]);

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

    public function syncAttendance(Request $request, string $serialNumber): JsonResponse
    {
        $validated = $request->validate([
            'records' => ['required', 'array'],
            'records.*.user_id' => ['required', 'string'],
            'records.*.timestamp' => ['required', 'date'],
            'records.*.punch_type' => ['nullable', 'integer', 'min:0', 'max:5'],
            'records.*.badge_number' => ['nullable', 'string'],
        ]);

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
