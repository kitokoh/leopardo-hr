<?php

declare(strict_types=1);

namespace App\Modules\Cameras\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Http\Controllers\Controller;
use App\Modules\Cameras\Domain\Models\Camera;
use App\Modules\Cameras\Domain\Models\CameraAlert;
use App\Modules\Cameras\Infrastructure\Services\CameraAlertService;
use App\Modules\Cameras\Interfaces\Api\V1\Requests\StoreCameraEventRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * Ingestion des événements de la chaîne vidéo (issue #7427, BC-19).
 *
 * `POST /internal/camera-events` — appelé par MediaMTX / le détecteur de
 * mouvement (machine-à-machine, **jamais** par un utilisateur) : l'accès est
 * protégé par le secret partagé MediaMTX
 * (`EnsureMediamtxSecretMiddleware`), exactement comme
 * `GET /internal/camera-token/verify`. Il n'y a donc pas de `auth:sanctum` ni
 * de middleware `tenant` ici : la société est déduite de la caméra ciblée, et
 * le contexte tenant est posé explicitement via `TenantManager::withinTenant()`
 * (comme le fait `EnsureTenantContext` pour les jobs) pour rester correct en
 * tenancy par schéma.
 *
 * Le contrôleur ne fabrique **aucune** donnée : il persiste l'événement reçu
 * puis délègue la création de l'alerte (dédupliquée) au service.
 */
class InternalCameraEventController extends Controller
{
    public function __construct(
        private readonly CameraAlertService $alerts,
        private readonly TenantManager $tenant,
    ) {}

    public function store(StoreCameraEventRequest $request): JsonResponse
    {
        $cameraId = $request->integer('camera_id');

        /** @var Camera|null $camera */
        $camera = Camera::withoutGlobalScopes()
            ->whereKey($cameraId)
            ->whereNull('deleted_at')
            ->first();

        if (! $camera instanceof Camera || ! $camera->is_active) {
            return new JsonResponse([
                'error' => 'CAMERA_NOT_FOUND',
                'message' => 'CAMERA_NOT_FOUND',
                'localized_message' => __('errors.CAMERA_NOT_FOUND'),
                'reason' => $camera instanceof Camera ? 'camera_inactive' : 'camera_unknown',
            ], 404);
        }

        $type = (string) $request->string('type');
        $severityInput = $request->input('severity');
        $severity = is_string($severityInput) && in_array($severityInput, CameraAlert::SEVERITIES, true)
            ? $severityInput
            : $this->alerts->severityFor($type);

        /** @var Company|null $company */
        $company = Company::query()->find($camera->company_id);

        if (! $company instanceof Company) {
            return new JsonResponse([
                'error' => 'CAMERA_NOT_FOUND',
                'message' => 'CAMERA_NOT_FOUND',
                'localized_message' => __('errors.CAMERA_NOT_FOUND'),
                'reason' => 'company_unknown',
            ], 404);
        }

        $detectedAtInput = $request->input('detected_at');
        $detectedAt = is_string($detectedAtInput) && $detectedAtInput !== ''
            ? Carbon::parse($detectedAtInput)
            : null;

        $snapshotInput = $request->input('snapshot_path');
        $metadataInput = $request->input('metadata');

        /** @var array{event: \App\Modules\Cameras\Domain\Models\CameraEvent, alert: CameraAlert, created: bool, notified: int} $result */
        $result = $this->tenant->withinTenant($company, function () use (
            $camera,
            $type,
            $severity,
            $detectedAt,
            $snapshotInput,
            $metadataInput,
        ): array {
            $event = $this->alerts->recordEvent(
                companyId: (string) $camera->company_id,
                cameraId: (int) $camera->id,
                type: $type,
                severity: $severity,
                detectedAt: $detectedAt,
                snapshotPath: is_string($snapshotInput) ? $snapshotInput : null,
                metadata: is_array($metadataInput) ? $metadataInput : [],
            );

            $outcome = $this->alerts->raiseAlert($event);

            return [
                'event' => $event,
                'alert' => $outcome['alert'],
                'created' => $outcome['created'],
                'notified' => $outcome['notified'],
            ];
        });

        return new JsonResponse([
            'data' => [
                'event_id' => $result['event']->id,
                'alert_id' => $result['alert']->id,
                'alert_created' => $result['created'],
                'notified' => $result['notified'],
            ],
        ], 201);
    }
}
