<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Pharmacy\Application\Services\PharmacySaleService;
use App\Modules\Pharmacy\Domain\Models\PharmacySale;
use App\Modules\Pharmacy\Domain\Models\PharmacySaleLine;
use App\Modules\Pharmacy\Interfaces\Api\V1\Requests\StorePharmacySaleRequest;
use App\Modules\Pharmacy\Interfaces\Api\V1\Requests\VoidPharmacySaleRequest;
use App\Modules\Pharmacy\Interfaces\Api\V1\Traits\ChecksPharmacySolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ventes comptoir (POS) — PHARMA-005 (#7802).
 *
 * Encaissement par tout employé du tenant (le comptoir est tenu par les
 * préparateurs), annulation réservée aux managers (PharmacySalePolicy).
 * Délivrance FEFO et contrôle ordonnance délégués à PharmacySaleService :
 * produit à ordonnance sans `prescription_id` → 422
 * PHARMACY_PRESCRIPTION_REQUIRED ; stock insuffisant → 422
 * PHARMACY_INSUFFICIENT_STOCK sans effet partiel. Totaux serveur.
 */
class PharmacySaleController extends Controller
{
    use ChecksPharmacySolution;

    public function __construct(private readonly PharmacySaleService $saleService) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', PharmacySale::class);

        $query = PharmacySale::query()->where('company_id', $actor->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->input('payment_method'));
        }

        if ($request->filled('from')) {
            $query->where('sold_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->where('sold_at', '<=', $request->date('to'));
        }

        $sales = $query->orderByDesc('sold_at')->orderByDesc('id')
            ->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($sales->items())->map(fn (PharmacySale $sale): array => $this->payload($sale)),
            'meta' => [
                'current_page' => $sales->currentPage(),
                'last_page' => $sales->lastPage(),
                'per_page' => $sales->perPage(),
                'total' => $sales->total(),
            ],
        ]);
    }

    public function store(StorePharmacySaleRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', PharmacySale::class);

        /** @var array{lines: list<array{product_id: int, quantity: int}>, payment_method: string, customer_name?: string|null, prescription_id?: int|null} $payload */
        $payload = $request->validated();

        $sale = $this->saleService->create(
            companyId: (string) $actor->company_id,
            lines: $payload['lines'],
            paymentMethod: $payload['payment_method'],
            customerName: $payload['customer_name'] ?? null,
            prescriptionId: $payload['prescription_id'] ?? null,
            employeeId: (int) $actor->getAttribute('id'),
        );

        return response()->json(['data' => $this->payload($sale, true)], 201);
    }

    public function show(Request $request, PharmacySale $sale): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($sale, $actor->company_id);
        $this->authorize('view', $sale);

        return response()->json(['data' => $this->payload($sale, true)]);
    }

    /**
     * Annulation (manager, raison obligatoire) : re-crédit des lots
     * d'origine par mouvements `return`, vente conservée `voided`.
     */
    public function void(VoidPharmacySaleRequest $request, PharmacySale $sale): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($sale, $actor->company_id);
        $this->authorize('void', $sale);

        /** @var array{reason: string} $payload */
        $payload = $request->validated();

        $voided = $this->saleService->void(
            sale: $sale,
            reason: $payload['reason'],
            employeeId: (int) $actor->getAttribute('id'),
        );

        return response()->json(['data' => $this->payload($voided, true)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(PharmacySale $sale, bool $withLines = false): array
    {
        $payload = [
            'id' => (int) $sale->getAttribute('id'),
            'number' => $sale->number,
            'sold_at' => $sale->sold_at->toIso8601String(),
            'customer_name' => $sale->customer_name,
            'prescription_id' => $sale->prescription_id,
            'payment_method' => $sale->payment_method,
            'total_amount' => $sale->total_amount,
            'status' => $sale->status,
            'sold_by_employee_id' => $sale->sold_by_employee_id,
            'void_reason' => $sale->void_reason,
            'voided_at' => $sale->voided_at?->toIso8601String(),
        ];

        if ($withLines) {
            $payload['lines'] = $sale->lines()->orderBy('id')->get()
                ->map(fn (PharmacySaleLine $line): array => [
                    'id' => (int) $line->getAttribute('id'),
                    'product_id' => $line->product_id,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price,
                    'tax_rate' => $line->tax_rate,
                    'line_total' => $line->line_total,
                ])
                ->all();
        }

        return $payload;
    }
}
