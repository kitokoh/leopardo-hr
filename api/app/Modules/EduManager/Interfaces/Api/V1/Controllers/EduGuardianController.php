<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\EduManager\Domain\Models\EduGuardian;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Domain\Models\EduStudentGuardian;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\LinkEduStudentGuardianRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\StoreEduGuardianRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Traits\ChecksEduSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API de gestion des responsables légaux — EDU-002 / EDU-013 (#5818, #5829).
 *
 * Complète le portail guardian (#5829) qui, sans ces routes, n'était
 * inatteignable : `POST /edu-manager/guardians/access-links` exige un
 * `guardian_id` DÉJÀ existant, et AUCUNE surface (API ni écran) ne permettait
 * de créer un responsable légal ni de le rattacher à un élève — le portail
 * parents existait donc sans pouvoir être alimenté (constat 2026-09-14, audit
 * du parcours client « propriétaire d'école »).
 *
 * RBAC : direction / RH (EduGuardianPolicy, EduStudentGuardianPolicy).
 * Isolation : tout est borné au tenant de l'acteur, un identifiant d'un autre
 * tenant répond 404 (fail-closed, aucune fuite de PII).
 */
class EduGuardianController extends Controller
{
    use ChecksEduSolution;

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', EduGuardian::class);

        $guardians = EduGuardian::query()
            ->where('company_id', $actor->company_id)
            ->withCount('students')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate((int) ($request->input('per_page') ?? 50));

        return response()->json([
            'data' => $guardians->getCollection()->map(fn (EduGuardian $guardian): array => $this->payload($guardian))->all(),
            'meta' => [
                'current_page' => $guardians->currentPage(),
                'per_page' => $guardians->perPage(),
                'total' => $guardians->total(),
            ],
        ]);
    }

    public function store(StoreEduGuardianRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', EduGuardian::class);

        /** @var EduGuardian $guardian */
        $guardian = EduGuardian::query()->create(array_merge($request->validated(), [
            'company_id' => $actor->company_id,
        ]));

        return response()->json(['data' => $this->payload($guardian)], 201);
    }

    /**
     * Rattache un responsable légal à un élève (idempotent : le UNIQUE
     * `(company_id, student_id, guardian_id)` rend un second appel sans effet
     * de bord, les drapeaux sont mis à jour).
     */
    public function link(LinkEduStudentGuardianRequest $request, EduStudent $student): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($student, $actor->company_id);
        $this->authorize('create', EduStudentGuardian::class);

        $data = $request->validated();

        /** @var EduGuardian $guardian */
        $guardian = EduGuardian::query()
            ->where('company_id', $actor->company_id)
            ->findOrFail((int) $data['guardian_id']);

        /** @var EduStudentGuardian $link */
        $link = EduStudentGuardian::query()->updateOrCreate(
            [
                'company_id' => $actor->company_id,
                'student_id' => $student->getAttribute('id'),
                'guardian_id' => $guardian->getAttribute('id'),
            ],
            [
                'relationship_code' => $data['relationship_code'] ?? $guardian->relationship_code,
                'can_view_grades' => (bool) ($data['can_view_grades'] ?? false),
                'can_receive_notifications' => (bool) ($data['can_receive_notifications'] ?? true),
            ],
        );

        return response()->json([
            'data' => [
                'student_id' => (int) $student->getAttribute('id'),
                'guardian' => $this->payload($guardian),
                'relationship_code' => (string) $link->relationship_code,
                'can_view_grades' => (bool) $link->can_view_grades,
                'can_receive_notifications' => (bool) $link->can_receive_notifications,
            ],
        ], 201);
    }

    /**
     * Retire le rattachement (le responsable légal lui-même est conservé).
     */
    public function unlink(Request $request, EduStudent $student, EduGuardian $guardian): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($student, $actor->company_id);
        $this->assertSameTenant($guardian, $actor->company_id);
        $this->authorize('delete', EduStudentGuardian::class);

        EduStudentGuardian::query()
            ->where('company_id', $actor->company_id)
            ->where('student_id', $student->getAttribute('id'))
            ->where('guardian_id', $guardian->getAttribute('id'))
            ->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(EduGuardian $guardian): array
    {
        return [
            'id' => (int) $guardian->getAttribute('id'),
            'first_name' => $guardian->first_name,
            'last_name' => $guardian->last_name,
            'contact_reference' => $guardian->contact_reference,
            'relationship_code' => (string) $guardian->relationship_code,
            'employee_id' => $guardian->employee_id,
            'students_count' => $guardian->students_count ?? null,
            'verified_at' => $guardian->verified_at?->toIso8601String(),
        ];
    }
}
