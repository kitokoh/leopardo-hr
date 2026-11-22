<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\EduManager\Domain\Access\EduAccess;
use App\Modules\EduManager\Domain\Models\EduFeePayment;
use App\Modules\EduManager\Interfaces\Api\V1\Traits\ChecksEduSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Journal des encaissements scolaires — Issue #9790.
 *
 * Le guichet encaisse (`POST /edu-manager/fee-charges/{charge}/payments`) mais
 * aucun endpoint ne relisait les paiements : ils n'étaient visibles qu'en
 * détail d'UNE charge, jamais en travers du guichet. Un caissier ne pouvait
 * donc pas vérifier sa journée, ni retrouver un encaissement par référence.
 *
 * Lecture seule, bornée au tenant, paginée (`meta.total`). RBAC : direction
 * uniquement (`EduAccess::isAdmin`) — les montants et les PII d'élèves sont en
 * jeu, même garde que `EduFeeChargeController`.
 */
class EduFeePaymentController extends Controller
{
    use ChecksEduSolution;

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless(EduAccess::isAdmin($actor), 403, 'EDU_FEE_ADMIN_ONLY');

        $query = EduFeePayment::query()
            ->where('company_id', $actor->company_id)
            ->with('charge:id,student_id,fee_type_id,currency,status');

        if ($request->filled('fee_charge_id')) {
            $query->where('fee_charge_id', (int) $request->input('fee_charge_id'));
        }

        if ($request->filled('method')) {
            $query->where('method', (string) $request->input('method'));
        }

        if ($request->filled('from')) {
            $query->where('paid_at', '>=', (string) $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('paid_at', '<=', (string) $request->input('to'));
        }

        // Filtre par élève : la colonne vit sur la charge, pas sur le paiement
        // — `whereHas` évite une jointure manuelle et reste borné au tenant.
        if ($request->filled('student_id')) {
            $studentId = (int) $request->input('student_id');
            $query->whereHas('charge', fn ($charge) => $charge->where('student_id', $studentId));
        }

        $payments = $query->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->paginate((int) ($request->input('per_page') ?? 15));

        return response()->json([
            'data' => collect($payments->items())
                ->map(fn (EduFeePayment $payment): array => $this->payload($payment))
                ->values(),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(EduFeePayment $payment): array
    {
        return [
            'id' => (int) $payment->getAttribute('id'),
            'fee_charge_id' => (int) $payment->fee_charge_id,
            'student_id' => $payment->relationLoaded('charge') && $payment->charge !== null
                ? (int) $payment->charge->student_id
                : null,
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
