<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FarmOs\Application\Actions\ScheduleFarmOperation;
use App\Modules\FarmOs\Application\Actions\TransitionFarmOperation;
use App\Modules\FarmOs\Domain\Enums\FarmCapability;
use App\Modules\FarmOs\Domain\Enums\FarmOperationStatus;
use App\Modules\FarmOs\Domain\Enums\FarmOperationType;
use App\Modules\FarmOs\Domain\Exceptions\FarmOperationTransitionException;
use App\Modules\FarmOs\Domain\Models\FarmOperation;
use App\Modules\FarmOs\Domain\Models\FarmSite;
use App\Modules\FarmOs\Domain\Services\FarmCapabilityGate;
use App\Modules\FarmOs\Infrastructure\Context\FarmCompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Farm OS — Opérations agricoles (interventions) : planification, suivi,
 * clôture, affectation, avancement et signalement d'un problème.
 *
 * Le type d'opération détermine une capacité supplémentaire exigée
 * ({@see FarmOperationType::requiredCapability()}) : une exploitation qui n'a
 * pas activé les intrants ne peut pas planifier de fertilisation.
 */
final class FarmOperationController
{
    public function __construct(
        private readonly ScheduleFarmOperation $schedule,
        private readonly TransitionFarmOperation $transition,
        private readonly FarmCapabilityGate $gate,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::WorkPlanning);

        $operations = FarmOperation::query()
            ->with(['parcel:id,name', 'farmSite:id,name'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('op_type'), fn ($query) => $query->where('op_type', $request->string('op_type')->toString()))
            ->when($request->filled('parcel_id'), fn ($query) => $query->where('parcel_id', $request->integer('parcel_id')))
            ->when($request->filled('assigned_to'), fn ($query) => $query->where('assigned_to', $request->integer('assigned_to')))
            ->when($request->filled('from'), fn ($query) => $query->where('planned_start_at', '>=', Carbon::parse($request->string('from')->toString())))
            ->when($request->filled('to'), fn ($query) => $query->where('planned_start_at', '<=', Carbon::parse($request->string('to')->toString())))
            ->orderBy('planned_start_at')
            ->paginate((int) $request->integer('per_page', 25));

        return response()->json([
            'data' => $operations->getCollection()->map(fn (FarmOperation $operation): array => $this->payload($operation))->all(),
            'meta' => ['current_page' => $operations->currentPage(), 'last_page' => $operations->lastPage(), 'total' => $operations->total()],
        ]);
    }

    public function today(Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::WorkPlanning);

        $today = Carbon::today();

        $operations = FarmOperation::query()
            ->with(['parcel:id,name'])
            ->when($request->filled('assigned_to'), fn ($query) => $query->where('assigned_to', $request->integer('assigned_to')))
            ->whereIn('status', [FarmOperationStatus::Planned->value, FarmOperationStatus::InProgress->value])
            ->whereDate('planned_start_at', $today)
            ->orderBy('priority', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $operations->map(fn (FarmOperation $operation): array => $this->payload($operation))->all(),
            'meta' => ['date' => $today->toDateString()],
        ]);
    }

    public function late(Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::WorkPlanning);

        $operations = FarmOperation::query()
            ->with(['parcel:id,name'])
            ->when($request->filled('assigned_to'), fn ($query) => $query->where('assigned_to', $request->integer('assigned_to')))
            ->whereIn('status', [FarmOperationStatus::Planned->value, FarmOperationStatus::InProgress->value])
            ->whereNotNull('planned_end_at')
            ->where('planned_end_at', '<', Carbon::now())
            ->orderBy('planned_end_at')
            ->limit(50)
            ->get();

        return response()->json(['data' => $operations->map(fn (FarmOperation $operation): array => $this->payload($operation))->all()]);
    }

    public function store(Request $request, FarmSite $site): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::WorkPlanning);

        $validated = $request->validate([
            'op_type' => ['required', Rule::in(FarmOperationType::values())],
            'title' => ['nullable', 'string', 'max:160'],
            'instructions' => ['nullable', 'string', 'max:4000'],
            'parcel_id' => ['nullable', 'integer'],
            'crop_cycle_id' => ['nullable', 'integer'],
            'priority' => ['nullable', Rule::in(FarmOperation::PRIORITIES)],
            'planned_start_at' => ['nullable', 'date'],
            'planned_end_at' => ['nullable', 'date', 'after_or_equal:planned_start_at'],
            'assigned_to' => ['nullable', 'integer'],
            'farm_team_id' => ['nullable', 'integer'],
            'quantity_value' => ['nullable', 'numeric', 'min:0'],
            'quantity_unit' => ['nullable', 'string', 'max:16'],
            'notes' => ['nullable', 'string', 'max:4000'],
            // Idempotence terrain : rejeu mobile/hors ligne.
            'client_operation_id' => ['nullable', 'string', 'max:64'],
        ]);

        $type = FarmOperationType::from($validated['op_type']);

        // Capacité supplémentaire exigée par le type d'opération.
        $this->gate->assertAllows($this->company(), $type->requiredCapability());

        $operation = $this->schedule->execute($this->company(), $site, $validated, $request->user()?->id);

        return response()->json(['data' => $this->payload($operation)], 201);
    }

    public function show(FarmOperation $operation): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::WorkPlanning);

        return response()->json(['data' => $this->payload($operation->load(['parcel:id,name']))]);
    }

    public function start(FarmOperation $operation, Request $request): JsonResponse
    {
        return $this->applyTransition($operation, FarmOperationStatus::InProgress, $request);
    }

    public function complete(FarmOperation $operation, Request $request): JsonResponse
    {
        return $this->applyTransition($operation, FarmOperationStatus::Done, $request);
    }

    public function skip(FarmOperation $operation, Request $request): JsonResponse
    {
        return $this->applyTransition($operation, FarmOperationStatus::Skipped, $request);
    }

    public function cancel(FarmOperation $operation, Request $request): JsonResponse
    {
        return $this->applyTransition($operation, FarmOperationStatus::Cancelled, $request);
    }

    /**
     * Affectation d'un ouvrier ou d'une équipe par le responsable.
     */
    public function assign(FarmOperation $operation, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'assigned_to' => ['nullable', 'integer'],
            'farm_team_id' => ['nullable', 'integer'],
            'responsible_id' => ['nullable', 'integer'],
        ]);

        $operation->fill($validated);
        $operation->save();

        return response()->json(['data' => $this->payload($operation->refresh())]);
    }

    /**
     * Déclaration d'avancement par l'ouvrier ou son chef d'équipe.
     */
    public function progress(FarmOperation $operation, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'progress_percent' => ['required', 'numeric', 'between:0,100'],
            'quantity_value' => ['nullable', 'numeric', 'min:0'],
            'quantity_unit' => ['nullable', 'string', 'max:16'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($operation->statusEnum()->isTerminal()) {
            return response()->json([
                'error' => 'OPERATION_IMMUTABLE',
                'message' => 'A closed farm operation cannot be updated directly.',
            ], 422);
        }

        $operation->fill([
            'progress_percent' => $validated['progress_percent'],
            'quantity_value' => $validated['quantity_value'] ?? $operation->quantity_value,
            'quantity_unit' => $validated['quantity_unit'] ?? $operation->quantity_unit,
        ]);

        if (isset($validated['note']) && $validated['note'] !== '') {
            $operation->notes = trim(($operation->notes ?? '')."\n".(string) $validated['note']);
        }

        $operation->save();

        return response()->json(['data' => $this->payload($operation->refresh())]);
    }

    /**
     * Signalement d'un problème depuis le terrain (blocage, panne, intrant manquant).
     */
    public function reportProblem(FarmOperation $operation, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $operation->notes = trim(($operation->notes ?? '')."\n[PROBLEME] ".(string) $validated['message']);
        $operation->save();

        return response()->json([
            'data' => [
                'id' => $operation->id,
                'noted' => true,
                'recorded_at' => Carbon::now()->toIso8601String(),
            ],
        ], 201);
    }

    private function applyTransition(FarmOperation $operation, FarmOperationStatus $target, Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::WorkPlanning);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
            'progress_percent' => ['nullable', 'numeric', 'between:0,100'],
            'quantity_value' => ['nullable', 'numeric', 'min:0'],
            'quantity_unit' => ['nullable', 'string', 'max:16'],
        ]);

        try {
            $updated = $this->transition->execute($operation, $target, $validated, $request->user()?->id);
        } catch (FarmOperationTransitionException $exception) {
            return response()->json(['error' => 'INVALID_TRANSITION', 'message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $this->payload($updated)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(FarmOperation $operation): array
    {
        return [
            'id' => $operation->id,
            'farm_site_id' => $operation->farm_site_id,
            'parcel_id' => $operation->parcel_id,
            'crop_cycle_id' => $operation->crop_cycle_id,
            'op_type' => $operation->op_type,
            'title' => $operation->title,
            'instructions' => $operation->instructions,
            'status' => $operation->status,
            'priority' => $operation->priority,
            'planned_start_at' => $operation->planned_start_at?->toIso8601String(),
            'planned_end_at' => $operation->planned_end_at?->toIso8601String(),
            'started_at' => $operation->started_at?->toIso8601String(),
            'finished_at' => $operation->finished_at?->toIso8601String(),
            'assigned_to' => $operation->assigned_to,
            'farm_team_id' => $operation->farm_team_id,
            'responsible_id' => $operation->responsible_id,
            'progress_percent' => $operation->progress_percent,
            'quantity' => $operation->quantity_value === null ? null : [
                'value' => $operation->quantity_value,
                'unit' => $operation->quantity_unit,
            ],
            // Une opération close est immuable : le front le sait, il ne
            // proposera pas de champ éditable. Le serveur refuse de toute façon.
            'is_mutable' => $operation->isMutable(),
        ];
    }

    private function company(): Company
    {
        return app(FarmCompanyContext::class)->current();
    }
}
