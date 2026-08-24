<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\LoanResource;
use App\Http\Resources\Api\V1\TrainingEnrollmentResource;
use App\Modules\HR\Domain\Models\Contract;
use App\Modules\HR\Domain\Models\Evaluation;
use App\Modules\HR\Domain\Models\TrainingEnrollment;
use App\Modules\Payroll\Domain\Models\EmployeeLoan;
use App\Modules\Payroll\Domain\Models\LoanRepayment;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SelfServiceController extends Controller
{
    public function myCareer(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        /** @var Collection<int, array{id: int|null, company_id: string|int|null, company_name: string|null, start_date: string|null, end_date: string|null, job_title: mixed, contract_type: string, status: string, current: bool}> $contracts */
        $contracts = Contract::query()
            ->where('employee_id', $user->id)
            ->where('company_id', $user->company_id)
            ->orderByDesc('start_date')
            ->get()
            ->map(fn (Contract $contract): array => [
                'id' => $contract->id,
                'company_id' => $contract->company_id,
                'company_name' => $user->company?->name,
                'start_date' => $contract->start_date?->toDateString(),
                'end_date' => $contract->end_date?->toDateString(),
                'job_title' => $contract->job_title,
                'contract_type' => $contract->contract_type,
                'status' => $contract->status,
                'current' => $contract->status === 'active' && $contract->end_date === null,
            ])
            ->values();

        if ($contracts->isEmpty()) {
            $contracts->push([
                'id' => null,
                'company_id' => $user->company_id,
                'company_name' => $user->company?->name,
                'start_date' => $user->contract_start?->toDateString(),
                'end_date' => $user->contract_end?->toDateString(),
                'job_title' => data_get($user->extra_data, 'job_title'),
                'contract_type' => $user->contract_type,
                'status' => $user->status,
                'current' => $user->status === 'active' && $user->contract_end === null,
            ]);
        }

        return response()->json([
            'data' => [
                'available_for_new_company' => $user->status !== 'active' || $user->company_id === null,
                'current_company_id' => $user->company_id,
                'current_company_name' => $user->company?->name,
                // Rétro-compatibilité : la timeline reste la liste des contrats
                // (consommée par les apps mobiles — EmployeeCareerEntry).
                'timeline' => $contracts,
                // Issue #5328 (G5) — historique unifié : contrats + évaluations
                // + career_events (#5259) + départ (#5324), ordonné et paginé.
                'career_history' => $this->careerHistory($user, $request),
            ],
        ]);
    }

    /**
     * Historique unifié du cycle de vie employé (issue #5328 — gap G5 de la
     * spec hr-lifecycle #5258 §5) : contrats + évaluations + évolutions de
     * carrière + départ, fusionnés dans une timeline ordonnée (date
     * décroissante) et paginée.
     *
     * Les sections career_events et départ sont activées automatiquement dès
     * que leurs tables existent (#5259 PR #5303, #5324 PR #5331) : le
     * endpoint reste mergeable sur main et complet après leur merge — aucune
     * dépendance de merge.
     *
     * RBAC : lecture seule du parcours de l'employé authentifié (route /me/*).
     *
     * @return array<string, mixed> payload de pagination Laravel
     */
    private function careerHistory(Employee $user, Request $request): array
    {
        $perPage = max(1, min(100, $request->integer('per_page', 20)));
        $page = max(1, $request->integer('page', 1));

        /** @var Collection<int, array<string, mixed>> $items */
        $items = collect();

        // ── Contrats ────────────────────────────────────────────────────────
        Contract::query()
            ->where('employee_id', $user->id)
            ->where('company_id', $user->company_id)
            ->orderByDesc('start_date')
            ->get()
            ->each(function (Contract $contract) use ($items): void {
                $items->push([
                    'type' => 'contract',
                    'label' => __('career.type_contract'),
                    // start_date est un Carbon non-nullable (cast date) — pas de ?->.
                    'date' => $contract->start_date->toDateString(),
                    'status' => $contract->status,
                    'data' => [
                        'id' => $contract->id,
                        'contract_type' => $contract->contract_type,
                        'job_title' => $contract->job_title,
                        'start_date' => $contract->start_date->toDateString(),
                        'end_date' => $contract->end_date?->toDateString(),
                        'status' => $contract->status,
                    ],
                ]);
            });

        // ── Évaluations ─────────────────────────────────────────────────────
        Evaluation::query()
            ->where('employee_id', $user->id)
            ->where('company_id', $user->company_id)
            ->orderByDesc('created_at')
            ->get()
            ->each(function (Evaluation $evaluation) use ($items): void {
                $label = __('career.type_evaluation');
                if ($evaluation->period !== null && $evaluation->period !== '') {
                    $label .= ' — '.$evaluation->period;
                }
                $items->push([
                    'type' => 'evaluation',
                    'label' => $label,
                    'date' => ($evaluation->acknowledged_at ?? $evaluation->created_at)?->toDateString(),
                    'status' => $evaluation->status,
                    'data' => [
                        'id' => $evaluation->id,
                        'period' => $evaluation->period,
                        'score' => $evaluation->score,
                        'status' => $evaluation->status,
                    ],
                ]);
            });

        // ── Évolutions de carrière (#5259) — activé quand la table existe ──
        if (Schema::hasTable('career_events')) {
            foreach (DB::table('career_events')->where('employee_id', $user->id)->orderByDesc('created_at')->get() as $event) {
                $label = __('career.type_career_event');
                $eventType = data_get($event, 'type');
                if (is_string($eventType) && $eventType !== '') {
                    $label .= ' — '.$eventType;
                }
                $items->push([
                    'type' => 'career_event',
                    'label' => $label,
                    'date' => $this->firstNonEmptyString($event, ['effective_date', 'applied_at', 'created_at']),
                    'status' => (string) (data_get($event, 'status') ?? ''),
                    'data' => (array) $event,
                ]);
            }
        }

        // ── Départ (#5324) — activé quand la table existe ──────────────────
        if (Schema::hasTable('employee_departures')) {
            foreach (DB::table('employee_departures')->where('employee_id', $user->id)->orderByDesc('created_at')->get() as $departure) {
                $label = __('career.type_departure');
                $reason = data_get($departure, 'departure_reason');
                if (is_string($reason) && $reason !== '') {
                    $label .= ' — '.$reason;
                }
                $items->push([
                    'type' => 'departure',
                    'label' => $label,
                    'date' => $this->firstNonEmptyString($departure, ['departed_at', 'last_working_day', 'created_at']),
                    'status' => (string) (data_get($departure, 'status') ?? 'departed'),
                    'data' => (array) $departure,
                ]);
            }
        }

        /** @var Collection<int, array<string, mixed>> $sorted */
        $sorted = $items->sortByDesc('date')->values();

        $paginator = new LengthAwarePaginator(
            $sorted->forPage($page, $perPage)->values(),
            $sorted->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return $paginator->toArray();
    }

    /**
     * Retourne la première valeur non vide d'une liste de clés sur un objet
     * de la base (stdClass), pour rester tolérant au schéma des sections
     * carrière/départ non encore mergées.
     *
     * @param  list<string>  $keys
     */
    private function firstNonEmptyString(object $row, array $keys): string
    {
        foreach ($keys as $key) {
            $value = data_get($row, $key);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return '';
    }

    public function myTrainings(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $enrollments = TrainingEnrollment::where('employee_id', $user->id)
            ->where('company_id', $user->company_id)
            ->with(['session.course:id,title,category,type,duration_hours'])
            ->orderByDesc('created_at')
            ->paginate(max(1, min(100, $request->integer('per_page', 20))));

        return TrainingEnrollmentResource::collection($enrollments)->response();
    }

    public function selfEnroll(Request $request, int $sessionId): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $exists = TrainingEnrollment::where('training_session_id', $sessionId)
            ->where('employee_id', $user->id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Already enrolled in this session.'], 422);
        }

        try {
            // #4978 : transaction imbriquée/savepoint — une violation unique
            // attendue est rollbackée localement, évitant l'état 25P02
            // (current transaction is aborted) qui contaminerait les requêtes
            // suivantes (notamment dans les tests de course).
            $enrollment = DB::transaction(fn (): TrainingEnrollment => TrainingEnrollment::create([
                'training_session_id' => $sessionId,
                'employee_id' => $user->id,
                'company_id' => $user->company_id,
                'status' => 'enrolled',
            ]));
        } catch (QueryException $e) {
            // Issue #3811 : course entre le exists() ci-dessus et le create()
            // (contrainte unique (training_session_id, employee_id)) — une
            // requête concurrente a gagné la course. 23505 = SQLSTATE
            // unique_violation (pattern PartnerService #3238) : réponse 422
            // idempotente, jamais de 500.
            if ($e->getCode() === '23505') {
                Log::warning("Training enrollment race for session {$sessionId}, employee {$user->id} — concurrent create won.");

                return response()->json(['message' => 'Already enrolled in this session.'], 422);
            }

            throw $e;
        }

        return (new TrainingEnrollmentResource($enrollment))
            ->response()
            ->setStatusCode(201);
    }

    public function myLoans(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $loans = EmployeeLoan::where('employee_id', $user->id)
            ->where('company_id', $user->company_id)
            ->withCount('repayments')
            ->orderByDesc('created_at')
            ->paginate(max(1, min(100, $request->integer('per_page', 20))));

        return LoanResource::collection($loans)->response();
    }

    public function myLoanRepayments(Request $request, int $loanId): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $loan = EmployeeLoan::where('employee_id', $user->id)
            ->where('company_id', $user->company_id)
            ->where('id', $loanId)
            ->firstOrFail();

        $repayments = LoanRepayment::where('employee_loan_id', $loan->id)
            ->orderBy('due_date')
            ->get();

        return response()->json(['data' => $repayments]); // LoanRepayment — no dedicated Resource yet
    }
}
