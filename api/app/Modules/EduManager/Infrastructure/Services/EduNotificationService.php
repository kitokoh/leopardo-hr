<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduAdmission;
use App\Modules\EduManager\Domain\Models\EduAttendance;
use App\Modules\EduManager\Domain\Models\EduReportCard;
use App\Modules\EduManager\Infrastructure\Jobs\SendEduNotificationJob;
use Illuminate\Database\Eloquent\Collection;

/**
 * Notifications EduManager — EDU-014 (issue #5830).
 *
 * V0 : la direction (principal/rh) est notifiée des événements scolaires
 * sensibles (admission convertie, absence enregistrée, bulletin publié)
 * via le hub transverse Notification. La notification directe des
 * responsables légaux (WhatsApp officiel, contrats PRE-006/PRE-007) arrive
 * avec le portail guardian (EDU-013).
 */
final class EduNotificationService
{
    public const TEMPLATE_ADMISSION_CONVERTED = 'edu_admission_converted';

    public const TEMPLATE_ABSENCE_RECORDED = 'edu_absence_recorded';

    public const TEMPLATE_REPORT_CARD_PUBLISHED = 'edu_report_card_published';

    public function admissionConverted(EduAdmission $admission): void
    {
        $studentName = trim($admission->applicant_first_name.' '.$admission->applicant_last_name);

        $this->dispatchToDirectors($admission->company_id, self::TEMPLATE_ADMISSION_CONVERTED, [
            'student_name' => $studentName,
            'admission_number' => $admission->admission_number,
            'category' => 'edu',
        ]);
    }

    public function absenceRecorded(EduAttendance $attendance): void
    {
        $studentName = $attendance->student?->display_name ?? 'Élève';

        $this->dispatchToDirectors($attendance->company_id, self::TEMPLATE_ABSENCE_RECORDED, [
            'student_name' => $studentName,
            'date' => $attendance->attendance_date?->toDateString() ?? '',
            'status' => $attendance->status,
            'category' => 'edu',
        ]);
    }

    public function reportCardPublished(EduReportCard $card): void
    {
        $studentName = $card->student?->display_name ?? 'Élève';

        $this->dispatchToDirectors($card->company_id, self::TEMPLATE_REPORT_CARD_PUBLISHED, [
            'student_name' => $studentName,
            'period' => $card->period,
            'category' => 'edu',
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function dispatchToDirectors(string $companyId, string $templateKey, array $context): void
    {
        /** @var Collection<int, int> $directorIds */
        $directorIds = Employee::query()
            ->where('company_id', $companyId)
            ->where('role', 'manager')
            ->whereIn('manager_role', ['principal', 'rh'])
            ->where('status', 'active')
            ->pluck('id');

        if ($directorIds->isEmpty()) {
            return;
        }

        SendEduNotificationJob::dispatch(
            $companyId,
            $directorIds->map(fn (int $id): int => $id)->all(),
            $templateKey,
            $context
        );
    }
}
