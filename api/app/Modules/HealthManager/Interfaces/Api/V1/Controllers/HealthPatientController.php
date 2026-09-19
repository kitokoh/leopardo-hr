<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\HealthManager\Domain\Models\HealthPatient;
use App\Modules\HealthManager\Infrastructure\Services\HealthMrnGenerator;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\StoreHealthPatientRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\UpdateHealthPatientRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Traits\ChecksHealthSolution;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * API du registre patients — HC-003 (#7787, BC-30).
 *
 * RBAC strict (HealthPatientPolicy deny-by-default) : direction et accueil
 * gèrent, praticiens actifs et facturation lisent, employé lambda 403.
 * MRN `PAT-YYYY-NNNN` généré côté serveur ; recherche (nom, MRN,
 * téléphone) paginée ; DELETE = ARCHIVAGE (soft delete, jamais de
 * suppression physique).
 */
class HealthPatientController extends Controller
{
    use ChecksHealthSolution;

    public function __construct(private readonly HealthMrnGenerator $mrnGenerator) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', HealthPatient::class);

        $query = HealthPatient::query()->where('company_id', $actor->company_id);

        // Registre archivé consultable explicitement (jamais par défaut).
        if ($request->boolean('with_archived')) {
            $query->withTrashed();
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Recherche : nom, MRN, téléphone (critère d'acceptation HC-003).
        $term = trim((string) $request->input('q', ''));
        if ($term !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
            $query->where(function (Builder $search) use ($like): void {
                $search->where('first_name', 'ilike', $like)
                    ->orWhere('last_name', 'ilike', $like)
                    ->orWhere('mrn', 'ilike', $like)
                    ->orWhere('phone', 'like', $like);
            });
        }

        $patients = $query->orderBy('last_name')->orderBy('first_name')
            ->paginate((int) ($request->input('per_page') ?? 15));

        return response()->json([
            'data' => collect($patients->items())->map(fn (HealthPatient $patient): array => $this->payload($patient)),
            'meta' => [
                'current_page' => $patients->currentPage(),
                'per_page' => $patients->perPage(),
                'total' => $patients->total(),
            ],
        ]);
    }

    public function store(StoreHealthPatientRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', HealthPatient::class);

        $validated = $request->validated();

        // MRN généré côté serveur DANS la transaction (lockForUpdate) —
        // jamais accepté depuis la requête ; UNIQUE(company_id, mrn) en
        // filet de sécurité.
        /** @var HealthPatient $patient */
        $patient = DB::transaction(function () use ($validated, $actor): HealthPatient {
            /** @var HealthPatient $created */
            $created = new HealthPatient($validated);
            $created->mrn = $this->mrnGenerator->next((string) $actor->company_id);
            $created->save();

            return $created;
        });

        return response()->json(['data' => $this->payload($patient)], 201);
    }

    public function show(Request $request, HealthPatient $patient): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($patient, $actor->company_id);
        $this->authorize('view', $patient);

        return response()->json(['data' => $this->payload($patient)]);
    }

    public function update(UpdateHealthPatientRequest $request, HealthPatient $patient): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($patient, $actor->company_id);
        $this->authorize('update', $patient);

        $patient->update($request->validated());

        return response()->json(['data' => $this->payload($patient->refresh())]);
    }

    /**
     * ARCHIVAGE — jamais de suppression physique (critère HC-003) :
     * statut `archived` + soft delete, le dossier et son MRN restent en
     * base (le MRN n'est jamais réutilisé).
     */
    public function destroy(Request $request, HealthPatient $patient): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($patient, $actor->company_id);
        $this->authorize('delete', $patient);

        $patient->update(['status' => HealthPatient::STATUS_ARCHIVED]);
        $patient->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(HealthPatient $patient): array
    {
        return [
            'id' => (int) $patient->getAttribute('id'),
            'mrn' => $patient->mrn,
            'first_name' => $patient->first_name,
            'last_name' => $patient->last_name,
            'sex' => $patient->sex,
            'birth_date' => $patient->birth_date,
            'blood_group' => $patient->blood_group,
            'phone' => $patient->phone,
            'email' => $patient->email,
            'address' => $patient->address,
            'emergency_contact_name' => $patient->emergency_contact_name,
            'emergency_contact_phone' => $patient->emergency_contact_phone,
            'emergency_contact_relationship' => $patient->emergency_contact_relationship,
            'insurance_provider' => $patient->insurance_provider,
            'insurance_number' => $patient->insurance_number,
            'allergies' => $patient->allergies,
            'medical_history' => $patient->medical_history,
            'status' => $patient->status,
            'archived_at' => $patient->deleted_at?->toISOString(),
        ];
    }
}
