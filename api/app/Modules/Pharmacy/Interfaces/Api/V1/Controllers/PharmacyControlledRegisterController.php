<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Pharmacy\Application\Services\PharmacySaleService;
use App\Modules\Pharmacy\Domain\Models\PharmacyPrescription;
use App\Modules\Pharmacy\Domain\Models\PharmacySale;
use App\Modules\Pharmacy\Domain\Models\PharmacyStockMovement;
use App\Modules\Pharmacy\Interfaces\Api\V1\Traits\ChecksPharmacySolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/**
 * Ordonnancier des produits contrôlés — PHARMA-006 (#7803).
 *
 * Registre chronologique en LECTURE SEULE, DÉRIVÉ du journal immuable
 * `pharmacy_stock_movements` (types sale/return des produits
 * `is_controlled`) : les annulations apparaissent en contre-passation
 * (`return`), jamais en effacement. Aucune route de mutation.
 *
 * Accès manager (pharmacy.compliance) — PII patients tenant-scopées.
 * Export CSV borné à la période demandée (`?from=&to=&format=csv`).
 */
class PharmacyControlledRegisterController extends Controller
{
    use ChecksPharmacySolution;

    public function index(Request $request): JsonResponse|Response
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewRegister', PharmacyPrescription::class);

        $query = PharmacyStockMovement::query()
            ->with(['batch', 'product'])
            ->where('pharmacy_stock_movements.company_id', $actor->company_id)
            ->whereIn('pharmacy_stock_movements.type', ['sale', 'return'])
            ->join('pharmacy_products', function ($join) use ($actor): void {
                $join->on('pharmacy_products.id', '=', 'pharmacy_stock_movements.product_id')
                    ->where('pharmacy_products.company_id', '=', $actor->company_id);
            })
            ->where('pharmacy_products.is_controlled', true)
            ->select('pharmacy_stock_movements.*')
            ->orderBy('pharmacy_stock_movements.created_at')
            ->orderBy('pharmacy_stock_movements.id');

        if ($request->filled('from')) {
            $query->whereDate('pharmacy_stock_movements.created_at', '>=', (string) $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('pharmacy_stock_movements.created_at', '<=', (string) $request->input('to'));
        }

        if ($request->input('format') === 'csv') {
            /** @var \Illuminate\Database\Eloquent\Collection<int, PharmacyStockMovement> $movements */
            $movements = $query->get();

            return $this->csv($this->entries($movements, (string) $actor->company_id));
        }

        $paginated = $query->paginate(max(1, min(100, $request->integer('per_page', 25))));

        /** @var \Illuminate\Database\Eloquent\Collection<int, PharmacyStockMovement> $items */
        $items = new \Illuminate\Database\Eloquent\Collection($paginated->items());

        return response()->json([
            'data' => $this->entries($items, (string) $actor->company_id)->values(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Enrichit chaque mouvement avec sa vente, son ordonnance et son
     * prescripteur (chargés en masse, tenant-scopés).
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, PharmacyStockMovement>  $movements
     * @return Collection<int, array<string, mixed>>
     */
    private function entries(\Illuminate\Database\Eloquent\Collection $movements, string $companyId): Collection
    {
        $saleIds = $movements
            ->filter(fn (PharmacyStockMovement $movement): bool => $movement->reference_type === PharmacySaleService::REFERENCE_TYPE && $movement->reference_id !== null)
            ->map(fn (PharmacyStockMovement $movement): int => (int) $movement->reference_id)
            ->unique()
            ->values();

        /** @var \Illuminate\Database\Eloquent\Collection<int, PharmacySale> $sales */
        $sales = PharmacySale::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('id', $saleIds)
            ->get()
            ->keyBy('id');

        $prescriptionIds = $sales
            ->map(fn (PharmacySale $sale): ?int => $sale->prescription_id)
            ->filter()
            ->unique()
            ->values();

        /** @var \Illuminate\Database\Eloquent\Collection<int, PharmacyPrescription> $prescriptions */
        $prescriptions = PharmacyPrescription::withoutGlobalScopes()
            ->with('prescriber')
            ->where('company_id', $companyId)
            ->whereIn('id', $prescriptionIds)
            ->get()
            ->keyBy('id');

        /** @var list<array<string, mixed>> $rows */
        $rows = [];

        foreach ($movements as $movement) {
            $sale = $movement->reference_id !== null ? $sales->get($movement->reference_id) : null;
            $prescription = $sale?->prescription_id !== null ? $prescriptions->get($sale->prescription_id) : null;

            $rows[] = [
                'movement_id' => (int) $movement->getAttribute('id'),
                'occurred_at' => $movement->created_at?->toIso8601String(),
                'direction' => $movement->type === 'sale' ? 'dispense' : 'return',
                'product_id' => $movement->product_id,
                'product_name' => $movement->product?->name,
                'batch_number' => $movement->batch?->batch_number,
                'quantity' => abs($movement->quantity_delta),
                'sale_number' => $sale?->number,
                'prescription_reference' => $prescription?->reference,
                'prescriber_name' => $prescription?->prescriber?->full_name,
                'patient_name' => $prescription?->patient_name,
                'dispensed_by_employee_id' => $movement->created_by_employee_id,
            ];
        }

        return collect($rows);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $entries
     */
    private function csv(Collection $entries): Response
    {
        $columns = [
            'occurred_at', 'direction', 'product_name', 'batch_number', 'quantity',
            'sale_number', 'prescription_reference', 'prescriber_name', 'patient_name',
            'dispensed_by_employee_id',
        ];

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            abort(500);
        }

        fputcsv($handle, $columns);

        foreach ($entries as $entry) {
            fputcsv($handle, array_map(
                static fn (string $column): string => (string) ($entry[$column] ?? ''),
                $columns
            ));
        }

        rewind($handle);
        $content = (string) stream_get_contents($handle);
        fclose($handle);

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="pharmacy-controlled-register.csv"',
        ]);
    }
}
