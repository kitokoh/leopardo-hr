<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Infrastructure\Services;

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
                'academic_year_id' => $card->academic_year_id,
                'average' => $card->average !== null ? (float) $card->average : null,
                'published_at' => $card->published_at?->toIso8601String(),
            ])
            ->all();
    }
}
