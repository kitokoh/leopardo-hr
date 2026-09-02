<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\TenantManager;
use App\Http\Controllers\Controller;
use App\Modules\Attendance\Domain\Models\ZktecoDevice;
use App\Modules\Attendance\Infrastructure\Services\ZktecoIntegrationService;
use Closure;
use Illuminate\Database\QueryException;
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

        $devices = $this->guardZktecoQuery(
            fn (): mixed => ZktecoDevice::query()
                ->where('company_id', $company->id)
                ->orderBy('name')
                ->get(),
        );

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
            // #5120 — méthodes de pointage configurables par tenant
            'punch_methods' => ['nullable', 'array'],
            'punch_methods.*' => ['string', 'in:fingerprint,face,card', 'distinct'],
        ]);

        $company = currentCompany();
        $device = $this->guardZktecoQuery(
            fn (): ZktecoDevice => $this->zktecoService->registerDevice($company->id, $validated),
        );

        // Sécurité #2216 : token de device généré à l'enregistrement, retourné
        // UNE SEULE FOIS en clair, stocké hashé côté serveur. Le client doit
        // l'envoyer en en-tête X-Device-Token sur heartbeat/sync-attendance.
        $plainToken = Str::random(48);
        $this->guardZktecoQuery(
            fn (): bool => (bool) $device->update(['sync_token_hash' => Hash::make($plainToken)]),
        );

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
        $device = $this->guardZktecoQuery(
            fn (): ZktecoDevice => ZktecoDevice::query()
                ->where('company_id', $company->id)
                ->findOrFail($id),
        );

        $plainToken = Str::random(48);
        $this->guardZktecoQuery(
            fn (): bool => (bool) $device->update(['sync_token_hash' => Hash::make($plainToken)]),
        );

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
        $device = $this->guardZktecoQuery(
            fn (): ZktecoDevice => ZktecoDevice::query()
                ->where('company_id', $company->id)
                ->findOrFail($id),
        );

        $syncHistory = $this->guardZktecoQuery(
            fn (): mixed => $this->zktecoService->getSyncHistory($device, 10),
        );

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
        $device = $this->guardZktecoQuery(
            fn (): ZktecoDevice => ZktecoDevice::query()
                ->where('company_id', $company->id)
                ->findOrFail($id),
        );

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'ip_address' => ['nullable', 'ip'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'protocol' => ['nullable', 'in:tcp,udp,cloud_api'],
            'location_label' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:online,offline,maintenance'],
            // #5120 — méthodes de pointage configurables par tenant
            'punch_methods' => ['nullable', 'array'],
            'punch_methods.*' => ['string', 'in:fingerprint,face,card', 'distinct'],
        ]);

        $this->guardZktecoQuery(fn (): bool => (bool) $device->update($validated));

        return new JsonResponse(['data' => $device->fresh()]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless($actor->isManager(), 403, 'FORBIDDEN');

        $company = currentCompany();
        $device = $this->guardZktecoQuery(
            fn (): ZktecoDevice => ZktecoDevice::query()
                ->where('company_id', $company->id)
                ->findOrFail($id),
        );

        $this->guardZktecoQuery(fn (): bool => (bool) $device->delete());

        return new JsonResponse(null, 204);
    }

    public function heartbeat(Request $request, string $serialNumber): JsonResponse
    {
        // #4934 : le device est authentifié par le middleware `zkteco.device`.
        /** @var ZktecoDevice $device */
        $device = $request->attributes->get('zkteco_device');

        $company = $device->company;
        abort_unless($company !== null, 404, 'RESOURCE_NOT_FOUND');

        // #4787 : traitement dans le contexte tenant du device (search_path)
        // — robuste en mode schema-par-tenant, neutre en mode shared.
        app(TenantManager::class)->withinTenant($company, function () use ($device): void {
            $this->zktecoService->heartbeat($device);
        });

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
            // #5121 — méthode de pointage (absent = rétro-compat fingerprint)
            'records.*.method' => ['nullable', 'string', 'in:fingerprint,face,card'],
        ]);

        // #4934 : le device est authentifié par le middleware `zkteco.device`.
        /** @var ZktecoDevice $device */
        $device = $request->attributes->get('zkteco_device');

        $company = $device->company;
        abort_unless($company !== null, 404, 'RESOURCE_NOT_FOUND');

        // #4787 : même principe — traitement scopé au schema du device.
        $syncLog = app(TenantManager::class)->withinTenant(
            $company,
            fn () => $this->zktecoService->pullAttendance($device, $validated['records']),
        );

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

        // #4187 : lookup SCOPE tenant — jamais de résolution par
        // serial_number seul (le serial est partagé entre tenants) :
        // un manager ne doit pouvoir pousser que sur SES appareils.
        $company = currentCompany();
        $device = $this->guardZktecoQuery(
            fn (): ZktecoDevice => ZktecoDevice::query()
                ->where('company_id', $company->id)
                ->where('serial_number', $serialNumber)
                ->firstOrFail(),
        );

        $syncLog = $this->guardZktecoQuery(fn (): mixed => $this->zktecoService->pushUsers($device));

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
        $device = $this->guardZktecoQuery(
            fn (): ZktecoDevice => ZktecoDevice::query()
                ->where('company_id', $company->id)
                ->findOrFail($id),
        );

        $logs = $this->guardZktecoQuery(
            fn (): mixed => $this->zktecoService->getSyncHistory($device, $request->integer('limit', 20)),
        );

        return new JsonResponse(['data' => $logs]);
    }

    /**
     * #6672 — dérive de schéma tenant (prod 2026-09-01) : les migrations
     * tenant `add_sync_token_hash_to_zkteco_devices_table` (2026-08-14),
     * `add_punch_methods_to_zkteco_devices` (2026-08-19) et
     * `zkteco_devices_company_id_uuid` (2026-08-19) n'ayant pas été rejouées
     * sur le schéma du tenant, toute requête scopée par `company_id` (UUID)
     * sur une colonne restée `bigint` lève `SQLSTATE[22P02] invalid input
     * syntax for type bigint`, et une colonne manquante lève `42703` → les
     * endpoints manager (`index`/`show`/`sync-logs`/…) répondaient
     * 500 INTERNAL_ERROR au lieu d'un état actionnable.
     *
     * Correctif : la requête est isolée dans un savepoint (transaction
     * imbriquée — sans effet en prod hors transaction, et sans empoisonner
     * la transaction du test/du service appelant) ; une dérive détectée
     * devient un 503 explicite (`ZKTECO_SCHEMA_DRIFT`) + trace d'audit, le
     * correctif réel étant `leopardo:migrate` sur le tenant (migrations
     * idempotentes). Sans cette garde, l'erreur reste opaque (500 générique)
     * et indistingable d'un vrai bug serveur.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    private function guardZktecoQuery(Closure $callback): mixed
    {
        DB::beginTransaction();

        try {
            $result = $callback();
            DB::commit();

            return $result;
        } catch (QueryException $exception) {
            DB::rollBack();

            if ($this->isZktecoSchemaDrift($exception)) {
                Log::channel('audit')->error('zkteco.schema_drift', [
                    'exception' => $exception->getMessage(),
                    'url' => request()->fullUrl(),
                    'actor_id' => request()->user()?->getAuthIdentifier(),
                ]);

                abort(503, 'ZKTECO_SCHEMA_DRIFT');
            }

            throw $exception;
        }
    }

    /**
     * Détecte les SQLSTATE caractéristiques d'un schéma tenant non migré :
     * 22P02 (conversion uuid→bigint impossible sur `company_id`) et 42703
     * (colonne absente). Toute autre erreur est relancée telle quelle.
     */
    private function isZktecoSchemaDrift(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['22P02', '42703'], true);
    }
}
