<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\EduManager\Domain\Access\EduAccess;
use App\Modules\EduManager\Domain\Models\EduFeeCharge;
use App\Modules\EduManager\Domain\Models\EduFeePayment;
use App\Modules\EduManager\Infrastructure\Services\EduFeeService;
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
 */
class EduFeeChargeController extends Controller
{
    use ChecksEduSolution;

    public function __construct(private readonly EduFeeService $fees) {}

    public function store(StoreEduFeeChargeRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertAdmin($actor);

        $charge = $this->fees->createCharge($actor, $request->validated());

        return response()->json(['data' => $this->chargePayload($charge)], 201);
    }

    public function payment(StoreEduFeePaymentRequest $request, int $charge): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertAdmin($actor);

        $model = $this->resolveCharge($actor, $charge);

        ['payment' => $payment, 'charge' => $updated] = $this->fees->recordPayment(
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

    public function waive(Request $request, int $charge): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertAdmin($actor);

        $model = $this->resolveCharge($actor, $charge);

        return response()->json(['data' => $this->chargePayload($this->fees->waiveCharge($actor, $model))]);
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
        return [
            'id' => (int) $charge->getAttribute('id'),
            'student_id' => (int) $charge->student_id,
            'fee_type_id' => (int) $charge->fee_type_id,
            'academic_year_id' => (int) $charge->academic_year_id,
            // `decimal` PostgreSQL remonte en chaîne : normalisé en nombre
            // pour les clients web/mobile (contrat JSON).
            'amount' => $this->numeric($charge->amount),
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
