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
 * Encaissement par tout employé du tenant, annulation (void) manager.
 * Délivrance FEFO, ordonnance exigée si produit sous prescription/contrôlé,
 * totaux serveur, prix figés.
 */
class PharmacySaleController extends Controller
{
    use ChecksPharmacySolution;

    public function __construct(private readonly PharmacySaleService $sales)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', PharmacySale::class);

        $query = PharmacySale::query()->with('lines')->where('company_id', $actor->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->input('payment_method'));
        }

        if ($request->filled('from')) {
            $query->whereDate('sold_at', '>=', (string) $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('sold_at', '<=', (string) $request->input('to'));
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

        /** @var array{payment_method: string, customer_name?: string|null, prescription_id?: int|null, lines: list<array{product_id: int, quantity: int}>} $payload */
        $payload = $request->validated();

        $sale = $this->sales->create(
            (string) $actor->company_id,
            array_map(static fn (array $line): array => [
                'product_id' => (int) $line['product_id'],
                'quantity' => (int) $line['quantity'],
            ], $payload['lines']),
            $payload['payment_method'],
            $payload['customer_name'] ?? null,
            isset($payload['prescription_id']) ? (int) $payload['prescription_id'] : null,
            (int) $actor->getAttribute('id'),
        );

        return response()->json(['data' => $this->payload($sale->load('lines'))], 201);
    }

    public function show(Request $request, PharmacySale $sale): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($sale, $actor->company_id);
        $this->authorize('view', $sale);

        return response()->json(['data' => $this->payload($sale->load('lines'))]);
    }

    public function void(VoidPharmacySaleRequest $request, PharmacySale $sale): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($sale, $actor->company_id);
        $this->authorize('void', $sale);

        /** @var array{reason: string} $payload */
        $payload = $request->validated();

        $voided = $this->sales->void($sale, $payload['reason'], (int) $actor->getAttribute('id'));

        return response()->json(['data' => $this->payload($voided->load('lines'))]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(PharmacySale $sale): array
    {
        return [
            'id' => (int) $sale->getAttribute('id'),
            'number' => $sale->number,
            'sold_at' => $sale->sold_at->toIso8601String(),
            'customer_name' => $sale->customer_name,
            'prescription_id' => $sale->prescription_id,
            'payment_method' => $sale->payment_method,
            'total_amount' => (string) $sale->total_amount,
            'status' => $sale->status,
            'void_reason' => $sale->void_reason,
            'voided_at' => $sale->voided_at?->toIso8601String(),
            'sold_by_employee_id' => $sale->sold_by_employee_id,
            'lines' => $sale->lines->map(fn (PharmacySaleLine $line): array => [
                'id' => (int) $line->getAttribute('id'),
                'product_id' => $line->product_id,
                'quantity' => $line->quantity,
                'unit_price' => (string) $line->unit_price,
                'tax_rate' => (string) $line->tax_rate,
                'line_total' => (string) $line->line_total,
            ])->all(),
        ];
    }
}
