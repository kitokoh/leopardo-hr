<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Attendance\Domain\Models\ZktecoDevice;
use App\Modules\Attendance\Infrastructure\Services\ZktecoIntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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

        // Sécurité #2216 : token de device généré à l'enregistrement, retourné
        // UNE SEULE FOIS en clair, stocké hashé côté serveur. Le client doit
        // l'envoyer en en-tête X-Device-Token sur heartbeat/sync-attendance.
        $plainToken = Str::random(48);
        $device->update(['sync_token_hash' => Hash::make($plainToken)]);

        return new JsonResponse([
            'data' => $device->fresh(),
            'device_token' => $plainToken,
        ], 201);
    }

    /**
     * Sécurité #2216 — rotation du token d'un device (manager uniquement).
     * L'ancien token est immédiatement révoqué.
     */
    public function regenerateToken(Request $request, int $id): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless($actor->isManager(), 403, 'FORBIDDEN');

        $company = currentCompany();
        $device = ZktecoDevice::query()
            ->where('company_id', $company->id)
            ->findOrFail($id);

        $plainToken = Str::random(48);
        $device->update(['sync_token_hash' => Hash::make($plainToken)]);

        Log::channel('audit')->info('zkteco_token.rotated', [
            'device_id' => $device->id,
            'serial_number' => $device->serial_number,
            'company_id' => $company->id,
            'actor_id' => $actor->id,
        ]);

        return new JsonResponse([
            'data' => $device->fresh(),
            'device_token' => $plainToken,
        ]);
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
        $device = $this->resolveAuthorizedDevice($request, $serialNumber);

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

        $device = $this->resolveAuthorizedDevice($request, $serialNumber);

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

    /**
     * Sécurité #2216 — résout le device par serial PUIS vérifie le token
     * X-Device-Token (hashé au repos). Fail-closed : un device sans token
     * configuré est rejeté (DEVICE_TOKEN_NOT_SET) jusqu'à rotation par le
     * manager. Les échecs sont journalisés sur le canal 'audit'.
     *
     * #4787 — le search_path PostgreSQL est réinitialisé avant le lookup
     * (pattern KioskController::resolveAuthorizedKiosk, issue #2689) : sur un
     * worker persistant, la requête précédente peut avoir basculé le
     * search_path vers le schéma d'un tenant → lookup cross-tenant ou 500
     * « table introuvable ». Restauration systématique en finally.
     */
    private function resolveAuthorizedDevice(Request $request, string $serialNumber): ZktecoDevice
    {
        // #4787 : lecture du search_path courant (variante nullsafe refusée par
        // PHPStan strict — garde is_object + property_exists, cf. #2973).
        $previous = 'public,shared_tenants';
        try {
            $searchPathRow = DB::selectOne('SHOW search_path');
            if (is_object($searchPathRow) && property_exists($searchPathRow, 'search_path')) {
                $previous = (string) $searchPathRow->search_path;
            }
        } catch (\Throwable) {
            // défaut conservé
        }
        DB::statement('SET search_path TO shared_tenants,public');

        try {
            $device = ZktecoDevice::query()
                ->where('serial_number', $serialNumber)
                ->firstOrFail();

            $token = (string) $request->header('X-Device-Token', '');

            if (empty($device->sync_token_hash)) {
                Log::channel('audit')->warning('zkteco_auth.not_configured', [
                    'serial_number' => $serialNumber,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                abort(401, 'DEVICE_TOKEN_NOT_SET');
            }

            if ($token === '' || ! Hash::check($token, (string) $device->sync_token_hash)) {
                Log::channel('audit')->warning('zkteco_auth.failed', [
                    'serial_number' => $serialNumber,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                abort(401, 'INVALID_DEVICE_TOKEN');
            }

            return $device;
        } finally {
            DB::statement('SET search_path TO '.$previous);
        }
    }

    public function pushUsers(Request $request, string $serialNumber): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless($actor->isManager(), 403, 'FORBIDDEN');

        // #4187 : lookup SCOPE tenant — jamais de résolution par
        // serial_number seul (le serial est partagé entre tenants) :
        // un manager ne doit pouvoir pousser que sur SES appareils.
        $company = currentCompany();
        $device = ZktecoDevice::query()
            ->where('company_id', $company->id)
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
