<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\EduManager\Application\Actions\CreateEduFeeChargeAction;
use App\Modules\EduManager\Application\Actions\RecordEduFeeBatchPaymentsAction;
use App\Modules\EduManager\Application\Actions\RecordEduFeePaymentAction;
use App\Modules\EduManager\Application\Actions\WaiveEduFeeChargeAction;
use App\Modules\EduManager\Domain\Access\EduAccess;
use App\Modules\EduManager\Domain\Models\EduFeeCharge;
use App\Modules\EduManager\Domain\Models\EduFeePayment;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\StoreEduFeeBatchPaymentRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\StoreEduFeeChargeRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\StoreEduFeePaymentRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Traits\ChecksEduSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Facturation scolaire v2 — Issue #5832 (EDU-016).
 *
 * Le contrat hérité (`/edu-manager/fees`, modèle `EduFee`) coexistait avec un
 * contrat de facturation détaillée (`/edu-manager/fee-charges`) dont le
 * service, les modèles, les requêtes de validation et les tables EXISTAIENT,
 * mais qu'aucune route ne servait : `EduFeeTest` (la spécification EDU-016)
 * répondait 404 sur les cinq scénarios du flux — impossible de facturer un
 * élève, d'encaisser un paiement (partiel/soldé), d'abandonner un solde ni de
 * consulter les écritures comptables du module.
 *
 * RBAC : direction uniquement (`EduAccess::isAdmin`), même garde que
 * `EduFeeTypeController` — les montants et les PII d'élèves sont en jeu.
 * Isolation : la résolution de route borne `{charge}` au tenant de l'acteur
 * (une charge d'un autre tenant → 404, jamais 403 : on ne révèle pas son
 * existence).
 *
 * #9790 — lecture (liste + détail) : seules les routes d'ÉCRITURE existaient,
 * donc l'écran de facturation n'avait aucun moyen de lister les charges ni de
 * connaître le solde restant d'une charge (le service ne l'expose nulle part).
 * `index` et `show` comblent ce trou ; le solde est calculé en SQL
 * (`sum(payments.amount)`) pour rester juste quel que soit le nombre
 * d'encaissements, sans hydrater les paiements.
 */
class EduFeeChargeController extends Controller
{
    use ChecksEduSolution;

    public function __construct(
        private readonly CreateEduFeeChargeAction $createCharge,
        private readonly RecordEduFeePaymentAction $recordPayment,
        private readonly RecordEduFeeBatchPaymentsAction $recordBatch,
        private readonly WaiveEduFeeChargeAction $waiveCharge,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertAdmin($actor);

        $query = EduFeeCharge::query()
            ->where('company_id', $actor->company_id)
            ->with([
                'student:id,student_number,display_name',
                'feeType:id,code,label',
            ]);

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', (int) $request->input('student_id'));
        }

        if ($request->filled('fee_type_id')) {
            $query->where('fee_type_id', (int) $request->input('fee_type_id'));
        }

        if ($request->filled('academic_year_id')) {
            $query->where('academic_year_id', (int) $request->input('academic_year_id'));
        }

        // Solde restant calculé en base : `withSum` évite d'hydrater les
        // paiements (une charge peut en porter des dizaines) tout en restant
        // exact — la somme est faite par PostgreSQL, pas en PHP.
        $query->withSum('payments as paid_amount', 'amount');

        $charges = $query->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate((int) ($request->input('per_page') ?? 15));

        return response()->json([
            'data' => collect($charges->items())
                ->map(fn (EduFeeCharge $charge): array => $this->chargePayload($charge))
                ->values(),
            'meta' => [
                'current_page' => $charges->currentPage(),
                'per_page' => $charges->perPage(),
                'total' => $charges->total(),
            ],
        ]);
    }

    public function show(Request $request, int $charge): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertAdmin($actor);

        $model = $this->resolveCharge($actor, $charge);

        $model->load([
            'student:id,student_number,display_name',
            'feeType:id,code,label',
            'payments',
        ]);

        $payments = $model->payments
            ->sortByDesc('paid_at')
            ->values()
            ->map(fn (EduFeePayment $payment): array => $this->paymentPayload($payment))
            ->all();

        return response()->json([
            'data' => array_merge($this->chargePayload($model), ['payments' => $payments]),
        ]);
    }

    public function store(StoreEduFeeChargeRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertAdmin($actor);

        $charge = $this->createCharge->execute($actor, $request->validated());

        return response()->json(['data' => $this->chargePayload($charge)], 201);
    }

    public function payment(StoreEduFeePaymentRequest $request, int $charge): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertAdmin($actor);

        $model = $this->resolveCharge($actor, $charge);

        ['payment' => $payment, 'charge' => $updated] = $this->recordPayment->execute(
            $actor,
            $model,
            $request->validated(),
        );

        return response()->json([
            'data' => [
                'payment' => $this->paymentPayload($payment),
                'charge' => $this->chargePayload($updated),
            ],
        ], 201);
    }

    /**
     * Encaissement EN LOT au guichet (#9791).
     *
     * Une classe entière règle le même jour : le caissier saisissait une charge
     * à la fois. Le lot porte le contexte commun (moyen, référence de
     * bordereau, date) et une ligne par charge.
     *
     * Réponse 201 quand tout est écrit ; 422 avec `errors[]` indexées quand au
     * moins une ligne est invalide — et dans ce cas RIEN n'est écrit (validation
     * intégrale avant la transaction, cf. RecordEduFeeBatchPaymentsAction).
     */
    public function batchPayment(StoreEduFeeBatchPaymentRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertAdmin($actor);

        $result = $this->recordBatch->execute($actor, $request->validated());

        if (! $result['ok']) {
            return response()->json([
                'error' => 'EDU_FEE_BATCH_INVALID',
                'message' => 'EDU_FEE_BATCH_INVALID',
                'errors' => $result['errors'],
                'summary' => $result['summary'],
            ], 422);
        }

        return response()->json([
            'data' => $result['results'],
            'summary' => $result['summary'],
        ], 201);
    }

    public function waive(Request $request, int $charge): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertAdmin($actor);

        $model = $this->resolveCharge($actor, $charge);

        return response()->json(['data' => $this->chargePayload($this->waiveCharge->execute($actor, $model))]);
    }

    /**
     * Charge du tenant de l'acteur — 404 hors tenant (aucune fuite).
     */
    private function resolveCharge(Employee $actor, int $chargeId): EduFeeCharge
    {
        /** @var EduFeeCharge $charge */
        $charge = EduFeeCharge::query()
            ->where('company_id', $actor->company_id)
            ->whereKey($chargeId)
            ->firstOrFail();

        return $charge;
    }

    private function assertAdmin(Employee $actor): void
    {
        abort_unless(EduAccess::isAdmin($actor), 403, 'EDU_FEE_ADMIN_ONLY');
    }

    /**
     * @return array<string, mixed>
     */
    private function chargePayload(EduFeeCharge $charge): array
    {
        $amount = $this->numeric($charge->amount);

        // `paid_amount` vient de `withSum` (liste) ou de la relation chargée
        // (détail) : les deux chemins donnent le même solde.
        $paid = $charge->getAttribute('paid_amount');
        if ($paid === null && $charge->relationLoaded('payments')) {
            $paid = $charge->payments->sum('amount');
        }
        $paid = $this->numeric($paid ?? 0) ?? 0;

        $remaining = is_numeric($amount) ? round((float) $amount - (float) $paid, 2) : null;

        return [
            'id' => (int) $charge->getAttribute('id'),
            'student_id' => (int) $charge->student_id,
            'student' => $charge->relationLoaded('student') && $charge->student !== null
                ? [
                    'id' => (int) $charge->student->getAttribute('id'),
                    'student_number' => $charge->student->student_number,
                    'display_name' => $charge->student->display_name,
                ]
                : null,
            'fee_type_id' => (int) $charge->fee_type_id,
            'fee_type' => $charge->relationLoaded('feeType') && $charge->feeType !== null
                ? [
                    'id' => (int) $charge->feeType->getAttribute('id'),
                    'code' => (string) $charge->feeType->code,
                    'label' => (string) $charge->feeType->label,
                ]
                : null,
            'academic_year_id' => (int) $charge->academic_year_id,
            // `decimal` PostgreSQL remonte en chaîne : normalisé en nombre
            // pour les clients web/mobile (contrat JSON).
            'amount' => $amount,
            'paid_amount' => $paid,
            'remaining_amount' => $remaining,
            'currency' => (string) $charge->currency,
            'status' => (string) $charge->status,
            'due_date' => $charge->due_date?->toDateString(),
            'external_id' => $charge->external_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentPayload(EduFeePayment $payment): array
    {
        return [
            'id' => (int) $payment->getAttribute('id'),
            'fee_charge_id' => (int) $payment->fee_charge_id,
            'amount' => $this->numeric($payment->amount),
            'currency' => (string) $payment->currency,
            'method' => (string) $payment->method,
            'reference' => $payment->reference,
            'external_id' => $payment->external_id,
            'paid_at' => $payment->paid_at->toIso8601String(),
        ];
    }

    private function numeric(mixed $raw): int|float|null
    {
        if (! is_numeric($raw)) {
            return null;
        }

        $value = (float) $raw;

        return $value === floor($value) ? (int) $value : $value;
    }
}
