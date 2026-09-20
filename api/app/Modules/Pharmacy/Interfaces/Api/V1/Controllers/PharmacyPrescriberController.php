<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Pharmacy\Domain\Models\PharmacyPrescriber;
use App\Modules\Pharmacy\Interfaces\Api\V1\Requests\StorePharmacyPrescriberRequest;
use App\Modules\Pharmacy\Interfaces\Api\V1\Requests\UpdatePharmacyPrescriberRequest;
use App\Modules\Pharmacy\Interfaces\Api\V1\Traits\ChecksPharmacySolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Prescripteurs — PHARMA-006 (#7803). Écriture manager, lecture employé du
 * tenant. Archivage, jamais de suppression (traçabilité réglementaire).
 */
class PharmacyPrescriberController extends Controller
{
    use ChecksPharmacySolution;

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', PharmacyPrescriber::class);

        $query = PharmacyPrescriber::query()->where('company_id', $actor->company_id);

        if ($request->filled('search')) {
            $needle = mb_strtolower((string) $request->input('search'));
            $like = '%'.addcslashes($needle, '%_\\').'%';
            $query->where(function ($sub) use ($like): void {
                $sub->whereRaw('LOWER(full_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(registration_number) LIKE ?', [$like]);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $prescribers = $query->orderBy('full_name')->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($prescribers->items())->map(fn (PharmacyPrescriber $prescriber): array => $this->payload($prescriber)),
            'meta' => [
                'current_page' => $prescribers->currentPage(),
                'last_page' => $prescribers->lastPage(),
                'per_page' => $prescribers->perPage(),
                'total' => $prescribers->total(),
            ],
        ]);
    }

    public function store(StorePharmacyPrescriberRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', PharmacyPrescriber::class);

        /** @var PharmacyPrescriber $prescriber */
        $prescriber = PharmacyPrescriber::query()->create(array_merge(
            ['status' => 'active'],
            $request->validated(),
            ['company_id' => $actor->company_id],
        ));

        return response()->json(['data' => $this->payload($prescriber->refresh())], 201);
    }

    public function show(Request $request, PharmacyPrescriber $prescriber): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($prescriber, $actor->company_id);
        $this->authorize('view', $prescriber);

        return response()->json(['data' => $this->payload($prescriber)]);
    }

    public function update(UpdatePharmacyPrescriberRequest $request, PharmacyPrescriber $prescriber): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($prescriber, $actor->company_id);
        $this->authorize('update', $prescriber);

        $prescriber->update($request->validated());

        return response()->json(['data' => $this->payload($prescriber->refresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(PharmacyPrescriber $prescriber): array
    {
        return [
            'id' => (int) $prescriber->getAttribute('id'),
            'full_name' => $prescriber->full_name,
            'registration_number' => $prescriber->registration_number,
            'specialty' => $prescriber->specialty,
            'phone' => $prescriber->phone,
            'status' => $prescriber->status,
        ];
    }
}
