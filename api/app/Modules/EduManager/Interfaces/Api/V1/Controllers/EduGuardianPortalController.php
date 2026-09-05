<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\EduManager\Domain\Models\EduAttendance;
use App\Modules\EduManager\Domain\Models\EduGuardian;
use App\Modules\EduManager\Domain\Models\EduReportCard;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Domain\Models\EduStudentGuardian;
use App\Modules\EduManager\Domain\Models\GuardianAccessToken;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\IssueGuardianAccessLinkRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\RedeemGuardianAccessLinkRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Traits\ChecksEduSolution;
use App\Modules\EduManager\Policies\EduGuardianPortalPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Portail guardian — EDU-013 (issue #5829).
 *
 * - Responsable lié à un employé : accès authentifié Sanctum
 *   (`/guardians/me/...`), Policy `EduGuardianPortalPolicy` (son portail
 *   uniquement) + `EduStudentPolicy` (enfants explicitement liés, notes
 *   seulement si `can_view_grades`).
 * - Responsable externe : lien d'accès EXPIRABLE émis par la direction,
 *   usage unique (hash sha256 stocké, token brut jamais persisté), échange
 *   une seule fois (`/access-links/{token}/redeem` — le token passe en body).
 * - Aucune énumération d'élèves : chaque lecture est bornée aux enfants
 *   liés ; consentement + audit (`edu.guardian.link_*`) tracés.
 */
class EduGuardianPortalController extends Controller
{
    use ChecksEduSolution;

    public function me(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        $guardian = EduGuardian::query()
            ->where('company_id', $actor->company_id)
            ->where('employee_id', $actor->id)
            ->first();

        if ($guardian === null) {
            return response()->json(['data' => null]);
        }

        $this->authorize('accessPortal', $guardian);

        return response()->json(['data' => $this->guardianPayload($guardian)]);
    }

    public function students(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $guardian = $this->guardianForActor($actor);

        if ($guardian === null) {
            return response()->json(['data' => []]);
        }

        // Enfants explicitement liés uniquement — aucune énumération globale.
        $students = $guardian->students()
            ->wherePivot('company_id', $actor->company_id)
            ->get(['edu_students.id', 'edu_students.student_number', 'edu_students.display_name']);

        return response()->json([
            'data' => $students->map(fn (EduStudent $student): array => [
                'id' => (int) $student->getAttribute('id'),
                'student_number' => $student->student_number,
                'display_name' => $student->display_name,
                'can_view_grades' => (bool) ($student->getRelation('pivot')->can_view_grades ?? false),
            ])->values(),
        ]);
    }

    public function presences(Request $request, EduStudent $student): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $guardian = $this->guardianForActor($actor);

        if ($guardian === null || ! $this->guardianCanViewStudent($guardian, $student, $actor)) {
            abort(404);
        }

        $attendances = EduAttendance::query()
            ->where('company_id', $actor->company_id)
            ->where('student_id', (int) $student->getAttribute('id'))
            ->orderByDesc('attendance_date')
            ->limit(90)
            ->get();

        return response()->json([
            'data' => $attendances->map(fn (EduAttendance $attendance): array => [
                'id' => (int) $attendance->getAttribute('id'),
                'date' => $attendance->attendance_date->toDateString(),
                'status' => $attendance->status,
            ])->values(),
        ]);
    }

    public function reportCards(Request $request, EduStudent $student): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $guardian = $this->guardianForActor($actor);

        if ($guardian === null || ! $this->guardianCanViewStudent($guardian, $student, $actor)) {
            abort(404);
        }

        // Notes sensibles : uniquement si can_view_grades sur le lien.
        if (! $this->guardianCanViewGrades($guardian, $student, $actor)) {
            abort(403, 'EDU_GUARDIAN_GRADES_FORBIDDEN');
        }

        $cards = EduReportCard::query()
            ->where('company_id', $actor->company_id)
            ->where('student_id', (int) $student->getAttribute('id'))
            ->where('status', EduReportCard::STATUS_PUBLISHED)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $cards->map(fn (EduReportCard $card): array => [
                'id' => (int) $card->getAttribute('id'),
                'period' => $card->period,
                'published_at' => $card->published_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function issueLink(IssueGuardianAccessLinkRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('issueLink', EduGuardian::class);

        /** @var EduGuardian $guardian */
        $guardian = EduGuardian::query()
            ->where('company_id', $actor->company_id)
            ->findOrFail((int) $request->input('guardian_id'));

        $ttlDays = (int) ($request->input('expires_in_days') ?? GuardianAccessToken::DEFAULT_TTL_DAYS);
        $plainToken = Str::random(64);
        $hash = hash('sha256', $plainToken);

        /** @var GuardianAccessToken $accessToken */
        $accessToken = GuardianAccessToken::query()->create([
            'company_id' => $actor->company_id,
            'guardian_id' => (int) $guardian->getAttribute('id'),
            'token_hash' => $hash,
            'expires_at' => now()->addDays($ttlDays),
            'created_by' => $actor->id,
        ]);

        AuditLog::create([
            'company_id' => $actor->company_id,
            'user_id' => $actor->id,
            'action' => 'edu.guardian.link_issued',
            'module' => 'edu',
            'auditable_type' => $accessToken->getMorphClass(),
            'auditable_id' => $accessToken->getAttribute('id'),
            'new_values' => ['guardian_id' => (int) $guardian->getAttribute('id'), 'ttl_days' => $ttlDays],
        ]);

        return response()->json([
            'data' => [
                'id' => (int) $accessToken->getAttribute('id'),
                'guardian_id' => (int) $guardian->getAttribute('id'),
                'token' => $plainToken, // affiché UNE seule fois, jamais persisté
                'expires_at' => $accessToken->expires_at->toIso8601String(),
            ],
        ], 201);
    }

    public function redeem(RedeemGuardianAccessLinkRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        $hash = hash('sha256', (string) $request->input('token'));

        /** @var GuardianAccessToken|null $accessToken */
        $accessToken = GuardianAccessToken::query()
            ->where('token_hash', $hash)
            ->first();

        if ($accessToken === null || ! $accessToken->isRedeemable()) {
            abort(422, 'EDU_GUARDIAN_LINK_INVALID');
        }

        $accessToken->update(['used_at' => now()]);

        AuditLog::create([
            'company_id' => $accessToken->company_id,
            'user_id' => null,
            'action' => 'edu.guardian.link_redeemed',
            'module' => 'edu',
            'auditable_type' => $accessToken->getMorphClass(),
            'auditable_id' => $accessToken->getAttribute('id'),
            'new_values' => ['guardian_id' => $accessToken->guardian_id],
        ]);

        /** @var EduGuardian $guardian */
        $guardian = EduGuardian::query()->findOrFail($accessToken->guardian_id);

        return response()->json(['data' => $this->guardianPayload($guardian)]);
    }

    private function guardianForActor(Employee $actor): ?EduGuardian
    {
        /** @var EduGuardian|null $guardian */
        $guardian = EduGuardian::query()
            ->where('company_id', $actor->company_id)
            ->where('employee_id', $actor->id)
            ->first();

        if ($guardian !== null) {
            $this->authorize('accessPortal', $guardian);
        }

        return $guardian;
    }

    private function guardianCanViewStudent(EduGuardian $guardian, EduStudent $student, Employee $actor): bool
    {
        if ($student->company_id !== $actor->company_id) {
            return false;
        }

        return EduStudentGuardian::query()
            ->where('company_id', $actor->company_id)
            ->where('guardian_id', (int) $guardian->getAttribute('id'))
            ->where('student_id', (int) $student->getAttribute('id'))
            ->exists();
    }

    private function guardianCanViewGrades(EduGuardian $guardian, EduStudent $student, Employee $actor): bool
    {
        return EduStudentGuardian::query()
            ->where('company_id', $actor->company_id)
            ->where('guardian_id', (int) $guardian->getAttribute('id'))
            ->where('student_id', (int) $student->getAttribute('id'))
            ->where('can_view_grades', true)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function guardianPayload(EduGuardian $guardian): array
    {
        return [
            'id' => (int) $guardian->getAttribute('id'),
            'first_name' => $guardian->first_name,
            'last_name' => $guardian->last_name,
            'relationship_code' => $guardian->relationship_code,
            'verified_at' => $guardian->verified_at?->toIso8601String(),
            'students' => $guardian->students()->get(['edu_students.id', 'edu_students.student_number', 'edu_students.display_name'])
                ->map(fn (EduStudent $student): array => [
                    'id' => (int) $student->getAttribute('id'),
                    'student_number' => $student->student_number,
                    'display_name' => $student->display_name,
                    'can_view_grades' => (bool) ($student->getRelation('pivot')->can_view_grades ?? false),
                ])->values(),
        ];
    }
}
