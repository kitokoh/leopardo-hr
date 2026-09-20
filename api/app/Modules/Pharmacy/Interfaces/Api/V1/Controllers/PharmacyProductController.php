<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Pharmacy\Domain\Models\PharmacyProduct;
use App\Modules\Pharmacy\Interfaces\Api\V1\Requests\StorePharmacyProductRequest;
use App\Modules\Pharmacy\Interfaces\Api\V1\Requests\UpdatePharmacyProductRequest;
use App\Modules\Pharmacy\Interfaces\Api\V1\Traits\ChecksPharmacySolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Référentiel produits d'officine — PHARMA-002 (#7799).
 *
 * deny-by-default (PharmacyProductPolicy) : écriture manager, lecture pour
 * tout employé du tenant. Recherche par nom, DCI, code-barres ou code
 * interne ; filtres catégorie / statut / ordonnance obligatoire.
 */
class PharmacyProductController extends Controller
{
    use ChecksPharmacySolution;

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', PharmacyProduct::class);

        $query = PharmacyProduct::query()->where('company_id', $actor->company_id);

        if ($request->filled('search')) {
            $needle = mb_strtolower((string) $request->input('search'));
            $like = '%'.addcslashes($needle, '%_\\').'%';
            $query->where(function ($sub) use ($like): void {
                $sub->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(dci) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(barcode) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(internal_code) LIKE ?', [$like]);
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('prescription_required')) {
            $query->where('prescription_required', $request->boolean('prescription_required'));
        }

        $products = $query->orderBy('name')->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($products->items())->map(fn (PharmacyProduct $product): array => $this->payload($product)),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function store(StorePharmacyProductRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', PharmacyProduct::class);

        /** @var PharmacyProduct $product */
        $product = PharmacyProduct::query()->create(array_merge(
            [
                'category' => 'medicament',
                'unit' => 'unite',
                'prescription_required' => false,
                'is_controlled' => false,
                'purchase_price' => 0,
                'sale_price' => 0,
                'tax_rate' => 0,
                'min_stock_level' => 0,
                'status' => 'active',
            ],
            $request->validated(),
            ['company_id' => $actor->company_id],
        ));

        return response()->json(['data' => $this->payload($product->refresh())], 201);
    }

    public function show(Request $request, PharmacyProduct $product): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($product, $actor->company_id);
        $this->authorize('view', $product);

        return response()->json(['data' => $this->payload($product)]);
    }

    public function update(UpdatePharmacyProductRequest $request, PharmacyProduct $product): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($product, $actor->company_id);
        $this->authorize('update', $product);

        $product->update($request->validated());

        return response()->json(['data' => $this->payload($product->refresh())]);
    }

    /**
     * Archivage (pas de suppression : le produit peut être référencé par des
     * lots, ventes et commandes — traçabilité réglementaire, PHARMA-002).
     */
    public function archive(Request $request, PharmacyProduct $product): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($product, $actor->company_id);
        $this->authorize('update', $product);

        $product->update(['status' => 'archived']);

        return response()->json(['data' => $this->payload($product->refresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(PharmacyProduct $product): array
    {
        return [
            'id' => (int) $product->getAttribute('id'),
            'name' => $product->name,
            'dci' => $product->dci,
            'form' => $product->form,
            'dosage' => $product->dosage,
            'barcode' => $product->barcode,
            'internal_code' => $product->internal_code,
            'category' => $product->category,
            'unit' => $product->unit,
            'prescription_required' => $product->prescription_required,
            'is_controlled' => $product->is_controlled,
            'purchase_price' => $product->purchase_price,
            'sale_price' => $product->sale_price,
            'tax_rate' => $product->tax_rate,
            'min_stock_level' => $product->min_stock_level,
            'status' => $product->status,
        ];
    }
}
