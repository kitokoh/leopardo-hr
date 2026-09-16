<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Modules\EduManager\Domain\Access\EduAccess;
use App\Modules\EduManager\Domain\Models\EduAttendance;
use App\Modules\EduManager\Domain\Models\EduGuardian;
use App\Modules\EduManager\Domain\Models\EduReportCard;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Domain\Models\EduStudentGuardian;
use App\Modules\EduManager\Domain\Models\GuardianAccessToken;
use App\Modules\EduManager\Infrastructure\Services\EduGuardianPortalService;
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

    /**
     * Durée de validité par défaut d'un lien de portail (jours).
     */
    private const DEFAULT_PORTAL_TTL_DAYS = 7;

    public function __construct(private readonly EduGuardianPortalService $portal) {}

    /**
     * Émet un lien de portail parents (direction uniquement) — EDU-013 (#5829).
     *
     * Le service `EduGuardianPortalService` (createLink/resolveToken/logAccess/
     * summary) et les tables `edu_guardian_portal_links` / `edu_portal_access_logs`
     * existaient déjà : seule la surface HTTP manquait, le portail parents
     * répondait 404 en toutes circonstances (constat 2026-09-14).
     *
     * RBAC d'abord (403), isolation ensuite (404) : un non-admin n'apprend pas
     * si le responsable existe.
     */
    public function issuePortalLink(Request $request, EduGuardian $guardian): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless(EduAccess::isAdmin($actor), 403, 'EDU_FEE_ADMIN_ONLY');
        abort_if($guardian->company_id !== $actor->company_id, 404);

        $days = (int) ($request->input('expires_in_days') ?? self::DEFAULT_PORTAL_TTL_DAYS);

        $link = $this->portal->createLink($actor, $guardian, $days);
        $token = (string) $link->portal_token;

        return response()->json([
            'data' => [
                'id' => (int) $link->getAttribute('id'),
                'guardian_id' => (int) $link->guardian_id,
                'token' => $token,
                'url' => url('/api/v1/edu-manager/portal/'.$token),
                'expires_at' => $link->expires_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Portail parents en LECTURE, ouvert par le token lui-même (route publique).
     *
     * Le token EST la credential : aucune session, aucun jeton Sanctum. Il est
     * résolu sans scope tenant (la société vient du lien, jamais de la requête),
     * expiré ou révoqué → 404. Chaque consultation est journalisée
     * (`edu_portal_access_logs`) et le périmètre reste borné aux enfants
     * explicitement liés — aucune énumération possible.
     */
    public function portal(string $token): JsonResponse
    {
        $link = $this->portal->resolveToken($token);

        abort_if($link === null, 404);

        $this->portal->logAccess($link);

        return response()->json(['data' => $this->portal->summary($link->refresh())]);
    }

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

    /**
     * Échange un lien d'accès parents contre le contenu du portail.
     *
     * CONTRAT ATTENDU PAR LA VITRINE (`front/web/src/app/guardian-portal/page.tsx`) :
     * `POST /edu-manager/guardian-portal/access-links/{token}/consume` →
     * `{ data: { guardian, children[] } }`, avec 404 (lien inconnu) et 410
     * (expiré ou déjà utilisé) — indistinguables côté client, l'audit serveur
     * garde la trace exacte.
     *
     * Cette route manquait : la vitrine livrée appelait un chemin inexistant
     * (le backend n'exposait que `/guardians/access-links/redeem`, token en
     * body) → le portail parents répondait 404 quoi qu'il arrive, même après
     * émission d'un lien valide. Constat 2026-09-14 (audit parcours client
     * « propriétaire d'école »).
     */
    public function consume(Request $request, string $token): JsonResponse
    {
        // ANONYME par construction : le parent détenteur du lien n'a pas de
        // session (c'est le principe même du lien expirable). Le contexte
        // tenant n'existe donc PAS ici : la solution est vérifiée sur la
        // société PORTEUSE du lien (jamais `currentCompany()`), et toutes les
        // lectures sont filtrées explicitement sur `company_id`. Un lien dont
        // la solution est inactive répond 404 — indistinguable d'un lien
        // inconnu, aucune fuite d'existence.
        if ($token === '') {
            abort(404, 'EDU_GUARDIAN_LINK_INVALID');
        }

        /** @var GuardianAccessToken|null $accessToken */
        $accessToken = GuardianAccessToken::query()
            ->withoutGlobalScopes()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if ($accessToken === null) {
            abort(404, 'EDU_GUARDIAN_LINK_INVALID');
        }

        $company = Company::query()->find($accessToken->company_id);

        if (! $company instanceof Company || ! FeatureFlag::enabled('edumanager', $company)) {
            abort(404, 'EDU_GUARDIAN_LINK_INVALID');
        }

        if (! $accessToken->isRedeemable()) {
            abort(410, 'EDU_GUARDIAN_LINK_USED_OR_EXPIRED');
        }

        $accessToken->update(['used_at' => now()]);

        AuditLog::create([
            'company_id' => $accessToken->company_id,
            'user_id' => null,
            'action' => 'edu.guardian.link_consumed',
            'module' => 'edu',
            'auditable_type' => $accessToken->getMorphClass(),
            'auditable_id' => (int) $accessToken->getAttribute('id'),
            'new_values' => ['guardian_id' => $accessToken->guardian_id],
        ]);

        /** @var EduGuardian $guardian */
        $guardian = EduGuardian::query()
            ->withoutGlobalScopes()
            ->where('company_id', $accessToken->company_id)
            ->findOrFail($accessToken->guardian_id);
        $companyId = (string) $accessToken->company_id;

        // Aucune énumération : uniquement les enfants EXPLICITEMENT liés.
        $students = $guardian->students()
            ->wherePivot('company_id', $companyId)
            ->get(['edu_students.id', 'edu_students.student_number', 'edu_students.display_name']);

        $children = $students->map(function (EduStudent $student) use ($guardian, $companyId): array {
            $pivot = $student->getRelation('pivot');
            $studentId = (int) $student->getAttribute('id');
            $canViewGrades = (bool) ($pivot->can_view_grades ?? false);

            $attendances = EduAttendance::query()
                ->where('company_id', $companyId)
                ->where('student_id', $studentId)
                ->where('attendance_date', '>=', now()->subDays(30)->toDateString())
                ->get();

            $cards = $canViewGrades
                ? EduReportCard::query()
                    ->where('company_id', $companyId)
                    ->where('student_id', $studentId)
                    ->where('status', EduReportCard::STATUS_PUBLISHED)
                    ->orderByDesc('created_at')
                    ->get()
                : collect();

            return [
                'id' => $studentId,
                'student_number' => $student->student_number,
                'display_name' => $student->display_name,
                'relationship_code' => (string) ($pivot->relationship_code ?? $guardian->relationship_code),
                'can_view_grades' => $canViewGrades,
                'presence' => [
                    'today_status' => EduAttendance::query()
                        ->where('company_id', $companyId)
                        ->where('student_id', $studentId)
                        ->whereDate('attendance_date', now()->toDateString())
                        ->value('status'),
                    'last_30_days' => $attendances->groupBy('status')->map->count()->all(),
                    'recorded_days' => $attendances->count(),
                ],
                // Tableaux PHP (pas de Collection) : évite la covariance de
                // template de `Collection` que PHPStan refuse et fige le
                // contrat JSON (`report_cards` = liste d'objets).
                'report_cards' => $cards->map(fn (EduReportCard $card): array => [
                    'id' => (int) $card->getAttribute('id'),
                    'period' => (string) $card->period,
                    'average' => $card->getAttribute('average_score'),
                    'published_at' => $card->published_at?->toIso8601String(),
                ])->values()->all(),
            ];
        })->values()->all();

        return response()->json([
            'data' => [
                'guardian' => $this->guardianPayload($guardian),
                'children' => $children,
            ],
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

        return $this->buildAccessLink($guardian, $actor, (int) ($request->input('expires_in_days') ?? GuardianAccessToken::DEFAULT_TTL_DAYS));
    }

    /**
     * Émission par CHEMIN (`POST /edu-manager/guardians/{guardian}/access-links`).
     *
     * Forme attendue par la vitrine livrée (cf. en-tête de
     * `front/web/src/app/guardian-portal/page.tsx`) : le backend n'exposait que
     * la variante « guardian_id en body », d'où un 404 côté écran direction.
     * Même RBAC et même TTL que `issueLink`.
     */
    public function issueLinkForGuardian(Request $request, EduGuardian $guardian): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($guardian, $actor->company_id);
        $this->authorize('issueLink', EduGuardian::class);

        return $this->buildAccessLink(
            $guardian,
            $actor,
            (int) ($request->input('expires_in_days') ?? GuardianAccessToken::DEFAULT_TTL_DAYS),
        );
    }

    private function buildAccessLink(EduGuardian $guardian, Employee $actor, int $ttlDays): JsonResponse
    {
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
