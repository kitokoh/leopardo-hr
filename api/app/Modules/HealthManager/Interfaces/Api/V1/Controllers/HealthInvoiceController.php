<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\HealthManager\Domain\Models\HealthInvoice;
use App\Modules\HealthManager\Domain\Models\HealthInvoiceItem;
use App\Modules\HealthManager\Domain\Models\HealthInvoicePayment;
use App\Modules\HealthManager\Domain\Models\HealthPatient;
use App\Modules\HealthManager\Infrastructure\Services\HealthInvoiceService;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\PayHealthInvoiceRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\StoreHealthInvoiceRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Traits\ChecksHealthSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Factures de soins & encaissements — HC-007 (#7791, BC-30).
 *
 * RBAC (HealthInvoicePolicy) : direction + facturation gèrent ; réception
 * en LECTURE seulement. Cycle de vie (spec §4) : draft → issued →
 * partially_paid → paid ; annulation possible tant que non payée. Aucune
 * route de modification : un brouillon se ré-émet ou s'annule, une facture
 * émise est IMMUABLE (annulation seulement). Totaux et numéro
 * `HINV-YYYY-NNNN` générés serveur (HealthInvoiceService).
 */
class HealthInvoiceController extends Controller
{
    use ChecksHealthSolution;

    public function __construct(private readonly HealthInvoiceService $invoices) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', HealthInvoice::class);

        $query = HealthInvoice::query()
            ->with('patient')
            ->where('company_id', $actor->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('patient_id')) {
            $query->where('patient_id', (int) $request->input('patient_id'));
        }

        $invoices = $query->orderByDesc('id')->paginate((int) ($request->input('per_page') ?? 50));

        return response()->json([
            'data' => collect($invoices->items())->map(fn (HealthInvoice $invoice): array => $this->payload($invoice)),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
            ],
        ]);
    }

    public function store(StoreHealthInvoiceRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', HealthInvoice::class);

        $invoice = $this->invoices->createDraft($actor, $request->validated());

        return response()->json(['data' => $this->payload($invoice->load(['patient', 'items']), withItems: true)], 201);
    }

    /**
     * Statistiques de facturation (spec HC-007) : compteurs par statut,
     * chiffre du mois (Σ encaissements du mois courant) et encours
     * (Σ total − amount_paid des factures issued/partially_paid).
     */
    public function stats(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', HealthInvoice::class);

        $companyId = (string) $actor->company_id;

        /** @var array<string, int> $byStatus */
        $byStatus = HealthInvoice::query()
            ->where('company_id', $companyId)
            ->select('status')
            ->get()
            ->countBy('status')
            ->all();

        $monthRevenue = (float) HealthInvoicePayment::query()
            ->where('company_id', $companyId)
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');

        $outstanding = (float) HealthInvoice::query()
            ->where('company_id', $companyId)
            ->whereIn('status', [HealthInvoice::STATUS_ISSUED, HealthInvoice::STATUS_PARTIALLY_PAID])
            ->selectRaw('COALESCE(SUM(total - amount_paid), 0) AS outstanding')
            ->value('outstanding');

        return response()->json([
            'data' => [
                'draft_count' => $byStatus[HealthInvoice::STATUS_DRAFT] ?? 0,
                'issued_count' => $byStatus[HealthInvoice::STATUS_ISSUED] ?? 0,
                'partially_paid_count' => $byStatus[HealthInvoice::STATUS_PARTIALLY_PAID] ?? 0,
                'paid_count' => $byStatus[HealthInvoice::STATUS_PAID] ?? 0,
                'cancelled_count' => $byStatus[HealthInvoice::STATUS_CANCELLED] ?? 0,
                'month_revenue' => round($monthRevenue, 2),
                'outstanding' => round($outstanding, 2),
                'currency' => currentCompany()->currency,
            ],
        ]);
    }

    public function show(Request $request, HealthInvoice $invoice): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($invoice, $actor->company_id);
        $this->authorize('view', $invoice);

        return response()->json([
            'data' => $this->payload($invoice->load(['patient', 'items', 'payments']), withItems: true, withPayments: true),
        ]);
    }

    public function issue(Request $request, HealthInvoice $invoice): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($invoice, $actor->company_id);
        $this->authorize('update', $invoice);

        $invoice = $this->invoices->issue($invoice);

        return response()->json(['data' => $this->payload($invoice->load(['patient', 'items']), withItems: true)]);
    }

    public function pay(PayHealthInvoiceRequest $request, HealthInvoice $invoice): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($invoice, $actor->company_id);
        $this->authorize('update', $invoice);

        $invoice = $this->invoices->pay($invoice, $request->validated());

        return response()->json([
            'data' => $this->payload($invoice->load(['patient', 'items', 'payments']), withItems: true, withPayments: true),
        ]);
    }

    public function cancel(Request $request, HealthInvoice $invoice): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($invoice, $actor->company_id);
        $this->authorize('update', $invoice);

        $invoice = $this->invoices->cancel($invoice);

        return response()->json(['data' => $this->payload($invoice->load('patient'))]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(HealthInvoice $invoice, bool $withItems = false, bool $withPayments = false): array
    {
        $data = [
            'id' => (int) $invoice->getAttribute('id'),
            'number' => $invoice->number,
            'patient_id' => $invoice->patient_id,
            'status' => $invoice->status,
            'currency' => $invoice->currency,
            'subtotal' => $invoice->subtotal,
            'discount' => $invoice->discount,
            'total' => $invoice->total,
            'amount_paid' => $invoice->amount_paid,
            'issued_at' => $invoice->issued_at?->toIso8601String(),
            'patient' => $invoice->relationLoaded('patient') && $invoice->patient instanceof HealthPatient
                ? [
                    'id' => (int) $invoice->patient->getAttribute('id'),
                    'mrn' => $invoice->patient->mrn,
                    'full_name' => $invoice->patient->full_name,
                ]
                : null,
        ];

        if ($withItems) {
            $data['items'] = $invoice->items->map(fn (HealthInvoiceItem $item): array => [
                'id' => (int) $item->getAttribute('id'),
                'care_act_id' => $item->care_act_id,
                'label' => $item->label,
                'unit_price' => $item->unit_price,
                'quantity' => $item->quantity,
                'line_total' => $item->line_total,
            ])->values()->all();
        }

        if ($withPayments) {
            $data['payments'] = $invoice->payments->map(fn (HealthInvoicePayment $payment): array => [
                'id' => (int) $payment->getAttribute('id'),
                'amount' => $payment->amount,
                'method' => $payment->method,
                'paid_at' => $payment->paid_at->toIso8601String(),
                'reference' => $payment->reference,
            ])->values()->all();
        }

        return $data;
    }
}
