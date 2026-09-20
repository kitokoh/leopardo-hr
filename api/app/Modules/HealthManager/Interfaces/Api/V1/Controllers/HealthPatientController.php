<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\HealthManager\Domain\Models\HealthPatient;
use App\Modules\HealthManager\Infrastructure\Services\HealthPatientNumberService;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\StoreHealthPatientRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\UpdateHealthPatientRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Traits\ChecksHealthSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API du registre patients — HC-003 (#7787, BC-31).
 *
 * RBAC (HealthPatientPolicy) : direction + réception gèrent, praticien en
 * lecture seule, employé lambda 403, cross-tenant 404. MRN `PAT-YYYY-NNNN`
 * généré serveur. Jamais de suppression physique : POST /archive → statut
 * `archived`.
 *
 * Recherche `q` : `full_name` ILIKE + `mrn` ILIKE UNIQUEMENT. Le téléphone
 * est chiffré au repos par le cast Laravel `encrypted` (non déterministe :
 * un même clair produit un chiffré différent à chaque écriture) — il est
 * donc IMPOSSIBLE de le filtrer en SQL, et un scan-déchiffrement de toute
 * la table serait O(n) en CPU crypto : la recherche par téléphone est
 * volontairement exclue (choix documenté, spec §3 chiffrement au repos).
 */
class HealthPatientController extends Controller
{
    use ChecksHealthSolution;

    /**
     * Clairs API ↔ colonnes chiffrées du modèle.
     *
     * @var array<string, string>
     */
    private const ENCRYPTED_INPUTS = [
        'birth_date' => 'birth_date_encrypted',
        'phone' => 'phone_encrypted',
        'email' => 'email_encrypted',
        'address' => 'address_encrypted',
        'emergency_contact_name' => 'emergency_contact_name_encrypted',
        'emergency_contact_phone' => 'emergency_contact_phone_encrypted',
        'insurance_provider' => 'insurance_provider_encrypted',
        'insurance_number' => 'insurance_number_encrypted',
        'allergies' => 'allergies_encrypted',
        'medical_history' => 'medical_history_encrypted',
    ];

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', HealthPatient::class);

        $query = HealthPatient::query()->where('company_id', $actor->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('q')) {
            $search = (string) $request->input('q');
            // full_name + mrn uniquement — phone chiffré non interrogeable
            // (voir en-tête de classe).
            $query->where(function ($builder) use ($search): void {
                $builder->where('full_name', 'ilike', "%{$search}%")
                    ->orWhere('mrn', 'ilike', "%{$search}%");
            });
        }

        $patients = $query->orderBy('full_name')->paginate((int) ($request->input('per_page') ?? 15));

        return response()->json([
            'data' => collect($patients->items())->map(fn (HealthPatient $patient): array => $this->payload($patient)),
            'meta' => [
                'current_page' => $patients->currentPage(),
                'per_page' => $patients->perPage(),
                'total' => $patients->total(),
            ],
        ]);
    }

    public function store(StoreHealthPatientRequest $request, HealthPatientNumberService $numberService): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', HealthPatient::class);

        $payload = $this->mapEncryptedInputs($request->validated());
        $payload['status'] = HealthPatient::STATUS_ACTIVE;

        // MRN serveur, séquence tenant/année race-safe (transaction + verrou).
        $patient = $numberService->createWithMrn((string) $actor->company_id, $payload);

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

        $patient->update($this->mapEncryptedInputs($request->validated()));

        return response()->json(['data' => $this->payload($patient->refresh())]);
    }

    /**
     * Archivage logique — les patients ne sont JAMAIS supprimés (spec §3).
     */
    public function archive(Request $request, HealthPatient $patient): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($patient, $actor->company_id);
        $this->authorize('delete', $patient);

        $patient->update(['status' => HealthPatient::STATUS_ARCHIVED]);

        return response()->json(['data' => $this->payload($patient->refresh())]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function mapEncryptedInputs(array $validated): array
    {
        foreach (self::ENCRYPTED_INPUTS as $input => $column) {
            if (array_key_exists($input, $validated)) {
                $validated[$column] = $validated[$input];
                unset($validated[$input]);
            }
        }

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(HealthPatient $patient): array
    {
        return [
            'id' => (int) $patient->getAttribute('id'),
            'mrn' => $patient->mrn,
            'full_name' => $patient->full_name,
            'birth_date' => $patient->birth_date_encrypted,
            'sex' => $patient->sex,
            'blood_group' => $patient->blood_group,
            'phone' => $patient->phone_encrypted,
            'email' => $patient->email_encrypted,
            'address' => $patient->address_encrypted,
            'emergency_contact_name' => $patient->emergency_contact_name_encrypted,
            'emergency_contact_phone' => $patient->emergency_contact_phone_encrypted,
            'insurance_provider' => $patient->insurance_provider_encrypted,
            'insurance_number' => $patient->insurance_number_encrypted,
            'allergies' => $patient->allergies_encrypted,
            'medical_history' => $patient->medical_history_encrypted,
            'status' => $patient->status,
        ];
    }
}
