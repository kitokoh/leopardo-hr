<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CRM\Domain\Enums\CrmAutomationTrigger;
use App\Modules\CRM\Domain\Exceptions\CrmAutomationException;
use App\Modules\CRM\Domain\Exceptions\CrmAutomationNotFoundException;
use App\Modules\CRM\Domain\Models\CrmAutomation;
use App\Modules\CRM\Domain\Models\CrmAutomationRun;
use App\Modules\CRM\Infrastructure\Services\AutomationEngine;
use App\Modules\CRM\Interfaces\Api\V1\Requests\StoreCrmAutomationRequest;
use App\Modules\CRM\Interfaces\Api\V1\Resources\CrmAutomationResource;
use App\Modules\CRM\Interfaces\Api\V1\Resources\CrmAutomationRunResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;

/**
 * Automatisations CRM (issue #5728).
 *
 * CRUD + activation/pause + simulation sans effet + historique + arrêt
 * d'urgence + point d'entrée événements (tenant-scope, principal/rh).
 */
class CrmAutomationController extends Controller
{
    public function __construct(private readonly AutomationEngine $engine) {}

    /** @return AnonymousResourceCollection<int, CrmAutomationResource> */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = CrmAutomation::query();
        if ($request->has('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        return CrmAutomationResource::collection(
            $query->orderByDesc('created_at')->paginate(50),
        );
    }

    public function store(StoreCrmAutomationRequest $request): JsonResponse
    {
        $automation = CrmAutomation::query()->create([
            'name' => (string) $request->validated('name'),
            'trigger_event' => (string) $request->validated('trigger_event'),
            'conditions' => $request->input('conditions', []),
            'actions' => $request->input('actions'),
            'status' => (string) $request->input('status', 'draft'),
            'version' => 1,
            'created_by' => (string) ($request->user()?->id ?? ''),
        ]);

        return (new CrmAutomationResource($automation))->response()->setStatusCode(201);
    }

    public function show(string $automation): JsonResponse
    {
        return (new CrmAutomationResource($this->automationOrFail($automation)))->response();
    }

    public function update(StoreCrmAutomationRequest $request, string $automation): JsonResponse
    {
        $model = $this->automationOrFail($automation);

        $model->forceFill([
            'name' => (string) $request->validated('name'),
            'trigger_event' => (string) $request->validated('trigger_event'),
            'conditions' => $request->input('conditions', []),
            'actions' => $request->input('actions'),
            'version' => $model->version + 1,
        ]);

        if ($request->has('status')) {
            $model->status = (string) $request->input('status');
        }

        $model->save();

        return (new CrmAutomationResource($model))->response();
    }

    public function destroy(string $automation): JsonResponse
    {
        $model = $this->automationOrFail($automation);
        $model->forceFill(['status' => 'disabled', 'archived_at' => now()])->save();

        return new JsonResponse(['data' => ['id' => $model->id, 'status' => 'disabled']]);
    }

    public function activate(string $automation): JsonResponse
    {
        $model = $this->automationOrFail($automation);
        $model->forceFill(['status' => 'active'])->save();

        return (new CrmAutomationResource($model))->response();
    }

    public function pause(string $automation): JsonResponse
    {
        $model = $this->automationOrFail($automation);
        $model->forceFill(['status' => 'paused'])->save();

        return (new CrmAutomationResource($model))->response();
    }

    public function simulate(Request $request, string $automation): JsonResponse
    {
        $model = $this->automationOrFail($automation);

        $context = $request->input('context', []);
        $result = $this->engine->simulate($model, is_array($context) ? $context : []);

        return new JsonResponse(['data' => $result]);
    }

    /** @return AnonymousResourceCollection<int, CrmAutomationRunResource> */
    public function runs(string $automation): AnonymousResourceCollection
    {
        $this->automationOrFail($automation);

        return CrmAutomationRunResource::collection(
            CrmAutomationRun::query()
                ->where('automation_id', $automation)
                ->orderByDesc('created_at')
                ->paginate(50),
        );
    }

    /**
     * Arrêt d'urgence des automatisations du tenant (body : enabled).
     */
    public function emergencyStop(Request $request): JsonResponse
    {
        $enabled = (bool) $request->input('enabled', false);
        $this->engine->setEmergencyStop($enabled);

        return new JsonResponse(['data' => ['enabled' => $this->engine->isEmergencyStopped() ? false : true]]);
    }

    /**
     * Point d'entrée des événements métier (test manuel + futur socle V0).
     */
    public function dispatch(Request $request, string $event): JsonResponse
    {
        if (! CrmAutomationTrigger::isValid($event)) {
            return new JsonResponse(['error' => 'CRM_AUTOMATION_INVALID_TRIGGER'], 422);
        }

        $context = $request->input('context', []);
        if (! is_array($context)) {
            $context = [];
        }

        try {
            $this->engine->dispatch($event, $context);
        } catch (CrmAutomationException $e) {
            Log::warning('CRM automation dispatch refusé', [
                'event' => $event,
                'code' => $e->errorCode(),
            ]);

            return new JsonResponse(['error' => $e->errorCode()], $e->httpStatus());
        }

        return new JsonResponse(['data' => ['event' => $event, 'received' => true]]);
    }

    private function automationOrFail(string $automationId): CrmAutomation
    {
        $model = CrmAutomation::query()->where('id', $automationId)->first();
        if ($model === null) {
            throw new CrmAutomationNotFoundException();
        }

        return $model;
    }
}
