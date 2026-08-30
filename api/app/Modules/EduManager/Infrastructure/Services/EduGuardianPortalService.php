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
            ->get()
            ->map(fn (EduReportCard $card): array => [
                'id' => (int) $card->getAttribute('id'),
                'period' => $card->period,
                'academic_year_id' => (int) $card->getAttribute('academic_year_id'),
                'published_at' => $card->published_at?->toIso8601String(),
            ])
            ->all();
    }
}
