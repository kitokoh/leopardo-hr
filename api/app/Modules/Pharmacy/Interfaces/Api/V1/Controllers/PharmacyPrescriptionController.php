<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Pharmacy\Domain\Models\PharmacyPrescriber;
use App\Modules\Pharmacy\Domain\Models\PharmacyPrescription;
use App\Modules\Pharmacy\Interfaces\Api\V1\Requests\StorePharmacyPrescriptionRequest;
use App\Modules\Pharmacy\Interfaces\Api\V1\Requests\UpdatePharmacyPrescriptionRequest;
use App\Modules\Pharmacy\Interfaces\Api\V1\Traits\ChecksPharmacySolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ordonnances — PHARMA-006 (#7803). Saisie par le comptoir (délivrance),
 * PII santé strictement tenant-scopées.
 */
class PharmacyPrescriptionController extends Controller
{
    use ChecksPharmacySolution;

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', PharmacyPrescription::class);

        $query = PharmacyPrescription::query()->with('prescriber')->where('company_id', $actor->company_id);

        if ($request->filled('search')) {
            $needle = mb_strtolower((string) $request->input('search'));
            $like = '%'.addcslashes($needle, '%_\\').'%';
            $query->where(function ($sub) use ($like): void {
                $sub->whereRaw('LOWER(reference) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(patient_name) LIKE ?', [$like]);
            });
        }

        if ($request->filled('prescriber_id')) {
            $query->where('prescriber_id', $request->integer('prescriber_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('prescribed_at', '>=', (string) $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('prescribed_at', '<=', (string) $request->input('to'));
        }

        $prescriptions = $query->orderByDesc('prescribed_at')->orderByDesc('id')
            ->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($prescriptions->items())->map(fn (PharmacyPrescription $prescription): array => $this->payload($prescription)),
            'meta' => [
                'current_page' => $prescriptions->currentPage(),
                'last_page' => $prescriptions->lastPage(),
                'per_page' => $prescriptions->perPage(),
                'total' => $prescriptions->total(),
            ],
        ]);
    }

    public function store(StorePharmacyPrescriptionRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', PharmacyPrescription::class);

        // Le prescripteur doit appartenir au tenant (404 sinon).
        $prescriber = PharmacyPrescriber::query()
            ->where('company_id', $actor->company_id)
            ->whereKey($request->integer('prescriber_id'))
            ->first();

        if ($prescriber === null) {
            abort(404);
        }

        /** @var PharmacyPrescription $prescription */
        $prescription = PharmacyPrescription::query()->create(array_merge(
            $request->validated(),
            ['company_id' => $actor->company_id],
        ));

        return response()->json(['data' => $this->payload($prescription->refresh()->load('prescriber'))], 201);
    }

    public function show(Request $request, PharmacyPrescription $prescription): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($prescription, $actor->company_id);
        $this->authorize('view', $prescription);

        return response()->json(['data' => $this->payload($prescription->load('prescriber'))]);
    }

    public function update(UpdatePharmacyPrescriptionRequest $request, PharmacyPrescription $prescription): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($prescription, $actor->company_id);
        $this->authorize('update', $prescription);

        /** @var array<string, mixed> $payload */
        $payload = $request->validated();

        if (array_key_exists('prescriber_id', $payload)) {
            $prescriber = PharmacyPrescriber::query()
                ->where('company_id', $actor->company_id)
                ->whereKey((int) $payload['prescriber_id'])
                ->first();

            if ($prescriber === null) {
                abort(404);
            }
        }

        $prescription->update($payload);

        return response()->json(['data' => $this->payload($prescription->refresh()->load('prescriber'))]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(PharmacyPrescription $prescription): array
    {
        return [
            'id' => (int) $prescription->getAttribute('id'),
            'prescriber_id' => $prescription->prescriber_id,
            'prescriber_name' => $prescription->prescriber?->full_name,
            'patient_name' => $prescription->patient_name,
            'patient_contact' => $prescription->patient_contact,
            'prescribed_at' => $prescription->prescribed_at->toDateString(),
            'reference' => $prescription->reference,
            'notes' => $prescription->notes,
        ];
    }
}
