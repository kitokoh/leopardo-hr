<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\EduManager\Domain\Access\EduAccess;
use App\Modules\EduManager\Domain\Models\EduFeePayment;
use App\Modules\EduManager\Infrastructure\Exports\FeePaymentsCsvExporter;
use App\Modules\EduManager\Infrastructure\Services\EduFeeBatchReceiptPdfRenderer;
use App\Modules\EduManager\Interfaces\Api\V1\Traits\ChecksEduSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function __construct(
        private readonly FeePaymentsCsvExporter $exporter,
        private readonly EduFeeBatchReceiptPdfRenderer $batchReceipt,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless(EduAccess::isAdmin($actor), 403, 'EDU_FEE_ADMIN_ONLY');

        $payments = $this->filteredQuery($request, $actor)
            ->orderByDesc('paid_at')
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
     * GET /edu-manager/fee-payments/export.csv (#9791).
     *
     * Mêmes filtres que `index`, sans pagination : le guichet remet un état de
     * caisse à la comptabilité et vérifie un dépôt bancaire contre la liste
     * réelle des encaissements. Le périmètre est construit par
     * `filteredQuery()` — donc borné au tenant, jamais élargi par l'export.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless(EduAccess::isAdmin($actor), 403, 'EDU_FEE_ADMIN_ONLY');

        $query = $this->filteredQuery($request, $actor)
            ->orderBy('paid_at')
            ->orderBy('id');

        $suffix = $request->filled('from') || $request->filled('to')
            ? '-'.($request->input('from') ?? 'debut').'_'.($request->input('to') ?? 'fin')
            : '';

        return response()->streamDownload(
            $this->exporter->generateCsvClosure($query),
            'encaissements'.$suffix.'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    /**
     * GET /edu-manager/fee-payments/batches/{batch}/receipt.pdf (#9793).
     *
     * Reçu PDF d'un ENCAISSEMENT EN LOT : le guichet remet une pièce au payeur
     * après un dépôt de classe. Sans elle, le caissier recopiait la liste des
     * élèves et le total à la main, et un parent n'avait rien à produire en cas
     * de contestation.
     *
     * Le lot est identifié par `batch_id` (colonne #9793) : `external_id` est
     * optionnel, donc il ne peut pas servir de regroupement fiable.
     *
     * Un lot inconnu du tenant → 404 : on ne révèle pas l'existence d'un lot
     * d'une autre école, et on ne rend pas un PDF vide qui ferait croire à un
     * encaissement nul.
     */
    public function batchReceipt(Request $request, string $batch): Response
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless(EduAccess::isAdmin($actor), 403, 'EDU_FEE_ADMIN_ONLY');

        $companyId = (string) $actor->company_id;

        abort_if($this->batchReceipt->countFor($companyId, $batch) === 0, 404, 'EDU_FEE_BATCH_NOT_FOUND');

        $rendered = $this->batchReceipt->render($companyId, $batch);

        return response($rendered['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$rendered['filename'].'"',
        ]);
    }

    /**
     * Requête filtrée partagée par la liste et l'export : une seule définition
     * du périmètre, donc aucune divergence entre ce qu'on voit et ce qu'on
     * exporte (et aucune fuite cross-tenant possible par l'export).
     *
     * @return \Illuminate\Database\Eloquent\Builder<EduFeePayment>
     */
    private function filteredQuery(Request $request, Employee $actor): \Illuminate\Database\Eloquent\Builder
    {
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

        return $query;
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
            'batch_id' => $payment->batch_id,
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
