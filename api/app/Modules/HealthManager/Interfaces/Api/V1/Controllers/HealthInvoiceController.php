<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\HealthManager\Domain\Exceptions\HealthInvalidStatusTransitionException;
use App\Modules\HealthManager\Domain\Exceptions\HealthInvoiceNotEditableException;
use App\Modules\HealthManager\Domain\Exceptions\HealthInvoiceOverpaymentException;
use App\Modules\HealthManager\Domain\Models\HealthCareAct;
use App\Modules\HealthManager\Domain\Models\HealthInvoice;
use App\Modules\HealthManager\Domain\Models\HealthInvoiceItem;
use App\Modules\HealthManager\Domain\Models\HealthInvoicePayment;
use App\Modules\HealthManager\Infrastructure\Services\HealthInvoiceNumberGenerator;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\StoreHealthInvoicePaymentRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\StoreHealthInvoiceRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\UpdateHealthInvoiceRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Traits\ChecksHealthSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * API des factures de soins — HC-007 (#7791, BC-30).
 *
 * `health.billing` et `health.admin` gèrent (réception, praticiens et
 * employé lambda : 403). Invariants (critères d'acceptation) :
 *   - total = Σ lignes − remise, RECALCULÉ CÔTÉ SERVEUR, prix FIGÉS depuis
 *     le catalogue à la facturation ;
 *   - paiement partiel → `partially_paid` avec solde exact, sur-paiement
 *     refusé (422 HEALTH_INVOICE_OVERPAYMENT), solde couvert → `paid` ;
 *   - facture ÉMISE non modifiable (422 HEALTH_INVOICE_NOT_EDITABLE),
 *     annulation seulement ; numéro HINV-YYYY-NNNN posé à l'émission.
 * Pas de DELETE : une facture ne s'efface pas (piste comptable).
 */
class HealthInvoiceController extends Controller
{
    use ChecksHealthSolution;

    public function __construct(private readonly HealthInvoiceNumberGenerator $numbers)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', HealthInvoice::class);

        $query = HealthInvoice::query()
            ->with(['items', 'payments'])
            ->where('company_id', $actor->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('patient_id')) {
            $query->where('patient_id', (int) $request->input('patient_id'));
        }

        $invoices = $query->orderByDesc('created_at')
            ->paginate((int) ($request->input('per_page') ?? 15));

        return response()->json([
            'data' => collect($invoices->items())->map(fn (HealthInvoice $invoice): array => $this->payload($invoice)),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
            ],
        ]);
    }

    /**
     * Création en BROUILLON : lignes construites côté serveur depuis le
     * catalogue (libellé + prix FIGÉS), total recalculé serveur — les
     * montants envoyés par le client sont IGNORÉS.
     */
    public function store(StoreHealthInvoiceRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', HealthInvoice::class);

        $validated = $request->validated();

        /** @var HealthInvoice $invoice */
        $invoice = DB::transaction(function () use ($validated, $actor): HealthInvoice {
            /** @var HealthInvoice $created */
            $created = new HealthInvoice([
                'patient_id' => (int) $validated['patient_id'],
                'discount' => $validated['discount'] ?? 0,
                'notes' => $validated['notes'] ?? null,
            ]);
            $created->status = HealthInvoice::STATUS_DRAFT;
            $created->save();

            /** @var array<int, array<string, int|string>> $items */
            $items = $validated['items'];
            $this->replaceItems($created, $items, $actor->company_id);

            return $created;
        });

        return response()->json(['data' => $this->payload($invoice->load(['items', 'payments']))], 201);
    }

    public function show(Request $request, HealthInvoice $invoice): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($invoice, $actor->company_id);
        $this->authorize('view', $invoice);

        return response()->json(['data' => $this->payload($invoice->load(['items', 'payments']))]);
    }

    /**
     * Mise à jour d'un BROUILLON uniquement : une facture émise n'est plus
     * modifiable (422 HEALTH_INVOICE_NOT_EDITABLE — annulation seulement).
     */
    public function update(UpdateHealthInvoiceRequest $request, HealthInvoice $invoice): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($invoice, $actor->company_id);
        $this->authorize('update', $invoice);

        if (! $invoice->isDraft()) {
            throw new HealthInvoiceNotEditableException;
        }

        $validated = $request->validated();

        DB::transaction(function () use ($invoice, $validated, $actor): void {
            $invoice->fill([
                'discount' => $validated['discount'] ?? $invoice->discount,
                'notes' => array_key_exists('notes', $validated) ? $validated['notes'] : $invoice->notes,
            ]);
            $invoice->save();

            if (isset($validated['items'])) {
                $invoice->items()->delete();
                /** @var array<int, array<string, int|string>> $items */
                $items = $validated['items'];
                $this->replaceItems($invoice, $items, $actor->company_id);
            } else {
                $this->recomputeTotals($invoice);
            }
        });

        return response()->json(['data' => $this->payload($invoice->refresh()->load(['items', 'payments']))]);
    }

    /**
     * Émission : brouillon → `issued`, numéro HINV-YYYY-NNNN posé (séquence
     * par tenant et par année, verrouillée sous transaction), horodatage.
     */
    public function issue(Request $request, HealthInvoice $invoice): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($invoice, $actor->company_id);
        $this->authorize('transition', $invoice);

        if (! $invoice->canTransitionTo(HealthInvoice::STATUS_ISSUED)) {
            throw new HealthInvalidStatusTransitionException($invoice->status, HealthInvoice::STATUS_ISSUED);
        }

        DB::transaction(function () use ($invoice, $actor): void {
            $invoice->number = $this->numbers->next($actor->company_id);
            $invoice->status = HealthInvoice::STATUS_ISSUED;
            $invoice->issued_at = now();
            $invoice->save();
        });

        return response()->json(['data' => $this->payload($invoice->refresh()->load(['items', 'payments']))]);
    }

    /**
     * Annulation : seul mouvement permis sur une facture émise (une facture
     * PAYÉE ne s'annule pas via l'API — 422).
     */
    public function cancel(Request $request, HealthInvoice $invoice): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($invoice, $actor->company_id);
        $this->authorize('transition', $invoice);

        if (! $invoice->canTransitionTo(HealthInvoice::STATUS_CANCELLED)) {
            throw new HealthInvalidStatusTransitionException($invoice->status, HealthInvoice::STATUS_CANCELLED);
        }

        $invoice->status = HealthInvoice::STATUS_CANCELLED;
        $invoice->cancelled_at = now();
        $invoice->save();

        return response()->json(['data' => $this->payload($invoice->refresh()->load(['items', 'payments']))]);
    }

    /**
     * Paiement : facture ÉMISE ou PARTIELLEMENT PAYÉE uniquement,
     * verrouillée sous transaction. Solde comparé en CENTIMES :
     * sur-paiement → 422 HEALTH_INVOICE_OVERPAYMENT, couverture exacte →
     * `paid`, sinon → `partially_paid`.
     */
    public function storePayment(StoreHealthInvoicePaymentRequest $request, HealthInvoice $invoice): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($invoice, $actor->company_id);
        $this->authorize('transition', $invoice);

        $validated = $request->validated();

        DB::transaction(function () use ($invoice, $validated, $actor): void {
            /** @var HealthInvoice $locked */
            $locked = HealthInvoice::query()
                ->where('company_id', $actor->company_id)
                ->whereKey($invoice->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($locked->status, HealthInvoice::PAYABLE_STATUSES, true)) {
                throw new HealthInvalidStatusTransitionException($locked->status, HealthInvoice::STATUS_PAID);
            }

            $locked->load('payments');
            $amountCents = (int) round(((float) $validated['amount']) * 100);
            $balanceCents = $locked->totalCents() - $locked->paidCents();

            if ($amountCents > $balanceCents) {
                throw new HealthInvoiceOverpaymentException;
            }

            /** @var HealthInvoicePayment $payment */
            $payment = new HealthInvoicePayment([
                'amount' => $validated['amount'],
                'method' => (string) $validated['method'],
                'paid_at' => isset($validated['paid_at'])
                    ? Carbon::parse((string) $validated['paid_at'])
                    : now(),
                'reference' => $validated['reference'] ?? null,
            ]);
            $payment->invoice_id = (int) $locked->getAttribute('id');
            $payment->save();

            $locked->status = $amountCents === $balanceCents
                ? HealthInvoice::STATUS_PAID
                : HealthInvoice::STATUS_PARTIALLY_PAID;
            $locked->save();
        });

        return response()->json(['data' => $this->payload($invoice->refresh()->load(['items', 'payments']))], 201);
    }

    /**
     * Stats simples (HC-007) : CA encaissé du mois courant (paiements) et
     * impayés (solde restant des factures émises/partiellement payées),
     * bornés au tenant.
     */
    public function stats(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', HealthInvoice::class);

        /** @var string|null $monthRevenue */
        $monthRevenue = HealthInvoicePayment::query()
            ->where('company_id', $actor->company_id)
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');

        /** @var list<HealthInvoice> $outstanding */
        $outstanding = HealthInvoice::query()
            ->with('payments')
            ->where('company_id', $actor->company_id)
            ->whereIn('status', HealthInvoice::OUTSTANDING_STATUSES)
            ->get()
            ->all();

        $outstandingCents = 0;
        foreach ($outstanding as $invoice) {
            $outstandingCents += max(0, $invoice->totalCents() - $invoice->paidCents());
        }

        return response()->json([
            'data' => [
                'month_revenue' => number_format((float) $monthRevenue, 2, '.', ''),
                'outstanding_total' => number_format($outstandingCents / 100, 2, '.', ''),
                'outstanding_count' => count($outstanding),
            ],
        ]);
    }

    /**
     * Reconstruit les lignes côté serveur depuis le catalogue (libellé et
     * prix FIGÉS au moment de la facturation) puis recalcule les totaux.
     *
     * @param  array<int, array<string, int|string>>  $items
     */
    private function replaceItems(HealthInvoice $invoice, array $items, string $companyId): void
    {
        foreach ($items as $item) {
            /** @var HealthCareAct $act */
            $act = HealthCareAct::query()
                ->where('company_id', $companyId)
                ->findOrFail((int) $item['care_act_id']);

            $quantity = (int) $item['quantity'];
            $lineCents = (int) round(((float) $act->price) * 100) * $quantity;

            /** @var HealthInvoiceItem $line */
            $line = new HealthInvoiceItem;
            $line->invoice_id = (int) $invoice->getAttribute('id');
            $line->care_act_id = (int) $act->getAttribute('id');
            $line->label = $act->name;
            $line->unit_price = $act->price;
            $line->quantity = $quantity;
            $line->line_total = number_format($lineCents / 100, 2, '.', '');
            $line->save();
        }

        $this->recomputeTotals($invoice);
    }

    /**
     * total = Σ lignes − remise (borné à 0, remise jamais supérieure au
     * sous-total — le CHECK en base est le filet).
     */
    private function recomputeTotals(HealthInvoice $invoice): void
    {
        $invoice->load('items');

        $subtotalCents = 0;
        foreach ($invoice->items as $item) {
            $subtotalCents += (int) round(((float) $item->line_total) * 100);
        }

        $discountCents = min((int) round(((float) $invoice->discount) * 100), $subtotalCents);

        $invoice->subtotal = number_format($subtotalCents / 100, 2, '.', '');
        $invoice->discount = number_format($discountCents / 100, 2, '.', '');
        $invoice->total = number_format(($subtotalCents - $discountCents) / 100, 2, '.', '');
        $invoice->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(HealthInvoice $invoice): array
    {
        $paidCents = $invoice->paidCents();

        return [
            'id' => (int) $invoice->getAttribute('id'),
            'patient_id' => $invoice->patient_id,
            'number' => $invoice->number,
            'subtotal' => $invoice->subtotal,
            'discount' => $invoice->discount,
            'total' => $invoice->total,
            'amount_paid' => number_format($paidCents / 100, 2, '.', ''),
            'balance' => number_format(($invoice->totalCents() - $paidCents) / 100, 2, '.', ''),
            'status' => $invoice->status,
            'notes' => $invoice->notes,
            'issued_at' => $invoice->issued_at?->toISOString(),
            'cancelled_at' => $invoice->cancelled_at?->toISOString(),
            'items' => $invoice->items->map(fn (HealthInvoiceItem $item): array => [
                'id' => (int) $item->getAttribute('id'),
                'care_act_id' => $item->care_act_id,
                'label' => $item->label,
                'unit_price' => $item->unit_price,
                'quantity' => $item->quantity,
                'line_total' => $item->line_total,
            ])->all(),
            'payments' => $invoice->payments->map(fn (HealthInvoicePayment $payment): array => [
                'id' => (int) $payment->getAttribute('id'),
                'amount' => $payment->amount,
                'method' => $payment->method,
                'paid_at' => $payment->paid_at->toISOString(),
                'reference' => $payment->reference,
            ])->all(),
        ];
    }
}
