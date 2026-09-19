<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Pharmacy\Domain\Models\PharmacySupplier;
use App\Modules\Pharmacy\Interfaces\Api\V1\Requests\StorePharmacySupplierRequest;
use App\Modules\Pharmacy\Interfaces\Api\V1\Requests\UpdatePharmacySupplierRequest;
use App\Modules\Pharmacy\Interfaces\Api\V1\Traits\ChecksPharmacySolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Fournisseurs d'officine — PHARMA-004 (#7801).
 *
 * deny-by-default (PharmacySupplierPolicy) : écriture manager, lecture pour
 * tout employé du tenant. Archivage — jamais de suppression (le fournisseur
 * peut être référencé par des lots et des commandes).
 */
class PharmacySupplierController extends Controller
{
    use ChecksPharmacySolution;

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', PharmacySupplier::class);

        $query = PharmacySupplier::query()->where('company_id', $actor->company_id);

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $suppliers = $query->orderBy('name')->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($suppliers->items())->map(fn (PharmacySupplier $supplier): array => $this->payload($supplier)),
            'meta' => [
                'current_page' => $suppliers->currentPage(),
                'last_page' => $suppliers->lastPage(),
                'per_page' => $suppliers->perPage(),
                'total' => $suppliers->total(),
            ],
        ]);
    }

    public function store(StorePharmacySupplierRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', PharmacySupplier::class);

        /** @var PharmacySupplier $supplier */
        $supplier = PharmacySupplier::query()->create(array_merge(
            ['type' => 'wholesaler', 'status' => 'active'],
            $request->validated(),
            ['company_id' => $actor->company_id],
        ));

        return response()->json(['data' => $this->payload($supplier->refresh())], 201);
    }

    public function show(Request $request, PharmacySupplier $supplier): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($supplier, $actor->company_id);
        $this->authorize('view', $supplier);

        return response()->json(['data' => $this->payload($supplier)]);
    }

    public function update(UpdatePharmacySupplierRequest $request, PharmacySupplier $supplier): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($supplier, $actor->company_id);
        $this->authorize('update', $supplier);

        $supplier->update($request->validated());

        return response()->json(['data' => $this->payload($supplier->refresh())]);
    }

    public function archive(Request $request, PharmacySupplier $supplier): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($supplier, $actor->company_id);
        $this->authorize('update', $supplier);

        $supplier->update(['status' => 'archived']);

        return response()->json(['data' => $this->payload($supplier->refresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(PharmacySupplier $supplier): array
    {
        return [
            'id' => (int) $supplier->getAttribute('id'),
            'name' => $supplier->name,
            'type' => $supplier->type,
            'contact_name' => $supplier->contact_name,
            'phone' => $supplier->phone,
            'email' => $supplier->email,
            'address' => $supplier->address,
            'status' => $supplier->status,
        ];
    }
}
