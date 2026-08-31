<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduAttendance;
use App\Modules\EduManager\Domain\Models\EduGuardian;
use App\Modules\EduManager\Domain\Models\EduGuardianPortalLink;
use App\Modules\EduManager\Domain\Models\EduPortalAccessLog;
use App\Modules\EduManager\Domain\Models\EduReportCard;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Domain\Models\EduStudentGuardian;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Issue #5829 (EDU-013) — portail guardian : liens expirables et résumé.
 *
 * - Génération : lien d'accès par `portal_token` (64 caractères aléatoires,
 *   indexé unique — le token EST la credential, pattern
 *   AccountingDocumentShare #5428), expiration bornée (1..30 jours) ;
 * - Résolution publique : O(1) par token, SANS scope tenant (les routes
 *   publiques n'ont pas de TenantMiddleware) — l'isolation est portée par le
 *   `company_id` du lien + des requêtes explicitement filtrées par tenant ;
 * - Pas d'énumération : le résumé ne renvoie QUE les enfants liés à CE
 *   guardian via edu_student_guardians (même tenant) ; les bulletins ne sont
 *   exposés que si `can_view_grades` et uniquement publiés ;
 * - Audit : chaque consultation est journalisée (edu_portal_access_logs) et
 *   `last_accessed_at` est mis à jour.
 */
final class EduGuardianPortalService
{
    public function __construct(private readonly EduOutboxPublisher $outbox)
    {
    }

    public const EVENT_PORTAL_LINK_CREATED = 'edu.guardian.portal_link_created.v1';

    /**
     * Crée un lien d'accès expirable pour un responsable légal.
     */
    public function createLink(Employee $actor, EduGuardian $guardian, int $expiresInDays): EduGuardianPortalLink
    {
        if ($guardian->company_id !== $actor->company_id) {
            throw new RuntimeException('Guardian does not belong to tenant.');
        }

        $days = max(1, min(30, $expiresInDays));

        /** @var EduGuardianPortalLink $link */
        $link = EduGuardianPortalLink::query()->create([
            'company_id' => $actor->company_id,
            'guardian_id' => (int) $guardian->getAttribute('id'),
            'portal_token' => Str::random(64),
            'expires_at' => now()->addDays($days),
            'created_by' => $actor->id,
        ]);

        $this->outbox->publish($actor->company_id, self::EVENT_PORTAL_LINK_CREATED, [
            'portal_link_id' => (int) $link->getAttribute('id'),
            'guardian_id' => (int) $guardian->getAttribute('id'),
            'expires_at' => $link->expires_at->toIso8601String(),
        ], 'portal-'.(int) $link->getAttribute('id'));

        return $link;
    }

    /**
     * Résout un token de portail (credential, O(1), sans scope tenant).
     */
    public function resolveToken(string $token): ?EduGuardianPortalLink
    {
        /** @var EduGuardianPortalLink|null $link */
        $link = EduGuardianPortalLink::query()
            ->withoutGlobalScope('company')
            ->where('portal_token', $token)
            ->first();

        if ($link === null || $link->isExpired() || $link->isRevoked()) {
            return null;
        }

        return $link;
    }

    /**
     * Journalise une consultation et rafraîchit `last_accessed_at`.
     */
    public function logAccess(EduGuardianPortalLink $link): void
    {
        DB::transaction(function () use ($link): void {
            EduPortalAccessLog::query()->create([
                'company_id' => $link->company_id,
                'guardian_id' => (int) $link->getAttribute('guardian_id'),
                'portal_link_id' => (int) $link->getAttribute('id'),
                'accessed_at' => now(),
            ]);

            $link->update(['last_accessed_at' => now()]);
        });
    }

    /**
     * Résumé du portail pour le guardian du lien (PII limitées au périmètre).
     *
     * @return array<string, mixed>
     */
    public function summary(EduGuardianPortalLink $link): array
    {
        /** @var EduGuardian $guardian */
        $guardian = EduGuardian::query()
            ->withoutGlobalScope('company')
            ->where('id', $link->guardian_id)
            ->where('company_id', $link->company_id)
            ->firstOrFail();

        $children = $this->authorizedChildren($link);
use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduAttendance;
use App\Modules\EduManager\Domain\Models\EduGuardian;
use App\Modules\EduManager\Domain\Models\EduGuardianAccessLink;
use App\Modules\EduManager\Domain\Models\EduReportCard;
use App\Modules\EduManager\Domain\Models\EduStudent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Portail responsable légal — Issue #5829 (EDU-013).
 *
 * Liens d'accès expirables à usage unique :
 *   - `createAccessLink()` : token aléatoire 256 bits (64 hex), seule son
 *     empreinte SHA-256 est persistée ; TTL borné [1, 30] jours (défaut 7) ;
 *     émission tracée dans `audit_logs` (`edu.access_link_created`).
 *   - `consume()` : validation empreinte + expiration + usage unique sous
 *     verrou (replay → 410), consentement RGPD (`verified_at` posé une
 *     seule fois), audit `edu.portal_access` ; retourne UNIQUEMENT les
 *     enfants explicitement liés au responsable (aucune énumération).
 *
 * Confidentialité : les bulletins ne sont renvoyés que s'ils sont PUBLIÉS
 * et si `can_view_grades` est vrai ; la présence est agrégée (pas de
 * commentaires ni d'informations nominatives tierces).
 */
final class EduGuardianPortalService
{
    public const DEFAULT_TTL_DAYS = 7;

    public const MAX_TTL_DAYS = 30;

    public const ERROR_LINK_INVALID = 'EDU_GUARDIAN_LINK_INVALID';

    public const ERROR_LINK_EXPIRED = 'EDU_GUARDIAN_LINK_EXPIRED';

    public const ERROR_LINK_ALREADY_USED = 'EDU_GUARDIAN_LINK_ALREADY_USED';

    /**
     * @param  array<string, mixed>  $data
     * @return array{token: string, link: EduGuardianAccessLink}
     */
    public function createAccessLink(Employee $actor, EduGuardian $guardian, array $data): array
    {
        abort_if($guardian->company_id !== $actor->company_id, 404);

        $ttlDays = isset($data['expires_in_days'])
            ? (int) $data['expires_in_days']
            : self::DEFAULT_TTL_DAYS;
        $ttlDays = max(1, min($ttlDays, self::MAX_TTL_DAYS));

        $token = Str::lower(bin2hex(random_bytes(32)));

        $link = EduGuardianAccessLink::query()->create([
            'company_id' => $guardian->company_id,
            'guardian_id' => (int) $guardian->getAttribute('id'),
            'token_hash' => hash('sha256', $token),
            'purpose' => $data['purpose'] ?? EduGuardianAccessLink::PURPOSE_PORTAL_ACCESS,
            'expires_at' => now()->addDays($ttlDays),
            'created_by' => $actor->id,
        ]);

        AuditLog::query()->create([
            'company_id' => $guardian->company_id,
            'user_id' => $actor->id,
            'action' => 'edu.access_link_created',
            'module' => 'edu_manager',
            'auditable_type' => EduGuardian::class,
            'auditable_id' => (int) $guardian->getAttribute('id'),
            'old_values' => null,
            'new_values' => null,
            'metadata' => [
                'category' => 'guardian_portal',
                'purpose' => $link->purpose,
                'expires_at' => $link->expires_at?->toIso8601String(),
                'link_id' => (int) $link->getAttribute('id'),
            ],
        ]);

        return ['token' => $token, 'link' => $link];
    }

    /**
     * Consomme un lien (usage unique) et renvoie le payload du portail.
     *
     * @return array<string, mixed>
     */
    public function consume(string $rawToken, Request $request): array
    {
        $token = Str::lower(trim($rawToken));
        $hash = hash('sha256', $token);

        /** @var EduGuardianAccessLink|null $link */
        $link = EduGuardianAccessLink::query()
            ->where('token_hash', $hash)
            ->first();

        if ($link === null) {
            abort(404, self::ERROR_LINK_INVALID);
        }

        if ($link->isExpired()) {
            abort(410, self::ERROR_LINK_EXPIRED);
        }

        return DB::transaction(function () use ($link, $request): array {
            /** @var EduGuardianAccessLink $locked */
            $locked = EduGuardianAccessLink::query()
                ->lockForUpdate()
                ->findOrFail((int) $link->getAttribute('id'));

            if ($locked->isUsed()) {
                abort(410, self::ERROR_LINK_ALREADY_USED);
            }

            if ($locked->isExpired()) {
                abort(410, self::ERROR_LINK_EXPIRED);
            }

            $locked->update(['used_at' => now()]);

            /** @var EduGuardian $guardian */
            $guardian = EduGuardian::query()->findOrFail((int) $locked->guardian_id);
            abort_if($guardian->company_id !== $locked->company_id, 404);

            // Consentement RGPD : posé une seule fois, à la première
            // consommation (acceptation explicite du portail).
            if ($guardian->verified_at === null) {
                $guardian->update(['verified_at' => now()]);
            }

            AuditLog::query()->create([
                'company_id' => $locked->company_id,
                'user_id' => null,
                'action' => 'edu.portal_access',
                'module' => 'edu_manager',
                'auditable_type' => EduGuardian::class,
                'auditable_id' => (int) $guardian->getAttribute('id'),
                'old_values' => null,
                'new_values' => null,
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 200),
                'metadata' => [
                    'category' => 'guardian_portal',
                    'purpose' => $locked->purpose,
                    'link_id' => (int) $locked->getAttribute('id'),
                ],
            ]);

            return $this->payload($guardian);
        });
    }

    /**
     * Payload portail : uniquement les enfants explicitement liés.
     *
     * @return array<string, mixed>
     */
    private function payload(EduGuardian $guardian): array
    {
        $children = [];

        foreach ($guardian->students()->get() as $student) {
            $canViewGrades = (bool) ($student->pivot?->getAttribute('can_view_grades') ?? false);

            $children[] = [
                'id' => (int) $student->getAttribute('id'),
                'student_number' => $student->student_number,
                'display_name' => $student->display_name,
                'status' => $student->status,
                'relationship_code' => (string) ($student->pivot?->getAttribute('relationship_code') ?? 'parent'),
                'can_view_grades' => $canViewGrades,
                'presence' => $this->presenceSummary($student),
                'report_cards' => $canViewGrades ? $this->publishedReportCards($student) : [],
            ];
        }

        return [
            'guardian' => [
                'id' => (int) $guardian->getAttribute('id'),
                'first_name' => $guardian->first_name,
                'last_name' => $guardian->last_name,
            ],
            'children' => $children->map(fn (EduStudent $student): array => $this->childPayload($student, $link))->values()->all(),
            'expires_at' => $link->expires_at->toIso8601String(),
        ];
    }

    /**
     * Enfants autorisés de CE guardian (même tenant, jamais d'énumération).
     *
     * @return Collection<int, EduStudent>
     */
    private function authorizedChildren(EduGuardianPortalLink $link): Collection
    {
        $studentIds = EduStudentGuardian::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $link->company_id)
            ->where('guardian_id', $link->guardian_id)
            ->pluck('student_id');

        return EduStudent::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $link->company_id)
            ->whereIn('id', $studentIds)
            ->orderBy('display_name')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function childPayload(EduStudent $student, EduGuardianPortalLink $link): array
    {
        $canViewGrades = EduStudentGuardian::query()
            ->where('company_id', $link->company_id)
            ->where('student_id', $student->getAttribute('id'))
            ->where('guardian_id', $link->guardian_id)
            ->where('can_view_grades', true)
            ->exists();

        return [
            'student_id' => (int) $student->getAttribute('id'),
            'student_number' => $student->student_number,
            'display_name' => $student->display_name,
            'attendance' => $this->attendanceSummary($student, $link->company_id),
            'report_cards' => $canViewGrades ? $this->publishedReportCards($student, $link->company_id) : [],
        ];
    }

    /**
     * @return array<string, int>
     */
    private function attendanceSummary(EduStudent $student, string $companyId): array
    {
        $since = now()->subDays(30);

        $rows = EduAttendance::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('student_id', $student->getAttribute('id'))
            ->where('attendance_date', '>=', $since->toDateString())
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'since' => $since->toDateString(),
            'present' => (int) ($rows['present'] ?? 0),
            'absent' => (int) ($rows['absent'] ?? 0),
            'late' => (int) ($rows['late'] ?? 0),
            'excused' => (int) ($rows['excused'] ?? 0),
                'verified_at' => $guardian->verified_at?->toIso8601String(),
            ],
            'children' => $children,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presenceSummary(EduStudent $student): array
    {
        $today = now()->toDateString();
        $since = now()->subDays(30)->startOfDay()->toDateString();

        $records = EduAttendance::query()
            ->where('company_id', $student->company_id)
            ->where('student_id', (int) $student->getAttribute('id'))
            ->where('attendance_date', '>=', $since)
            ->get(['attendance_date', 'status']);

        $todayRecord = $records->firstWhere('attendance_date', $today);
        $statusCounts = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0];

        foreach ($records as $record) {
            $status = $record->status;
            if (isset($statusCounts[$status])) {
                ++$statusCounts[$status];
            }
        }

        return [
            'today_status' => $todayRecord?->status,
            'last_30_days' => $statusCounts,
            'recorded_days' => $records->count(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function publishedReportCards(EduStudent $student, string $companyId): array
    {
        return EduReportCard::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('student_id', $student->getAttribute('id'))
            ->where('status', EduReportCard::STATUS_PUBLISHED)
            ->orderByDesc('published_at')
            ->limit(5)
    private function publishedReportCards(EduStudent $student): array
    {
        return EduReportCard::query()
            ->where('company_id', $student->company_id)
            ->where('student_id', (int) $student->getAttribute('id'))
            ->where('status', EduReportCard::STATUS_PUBLISHED)
            ->orderByDesc('period')
            ->get()
            ->map(fn (EduReportCard $card): array => [
                'id' => (int) $card->getAttribute('id'),
                'period' => $card->period,
                'academic_year_id' => (int) $card->getAttribute('academic_year_id'),
                'academic_year_id' => $card->academic_year_id,
                'average' => $card->average !== null ? (float) $card->average : null,
                'published_at' => $card->published_at?->toIso8601String(),
            ])
            ->all();
    }
}
