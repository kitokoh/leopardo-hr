<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduAttendance;
use App\Modules\EduManager\Domain\Models\EduExport;
use App\Modules\EduManager\Domain\Models\EduGrade;
use App\Modules\EduManager\Domain\Models\EduStudent;
use Illuminate\Support\Str;

/**
 * Export CSV sécurisé EduManager — EDU-017 (issue #5833).
 *
 * - Tenant-scope strict (company_id) ; aucun détail PII hors périmètre.
 * - Audit : chaque export est enregistré dans `edu_exports` (qui, quoi,
 *   quand, combien) — trace non altérable des accès aux données scolaires.
 * - CSV : `CsvCellSanitizer` (injection de formules) + masquage des champs
 *   sensibles non nécessaires (birth_date exclue des exports par défaut).
 */
final class EduExportService
{
    /**
     * @return array{filename: string, content: string, count: int}
     */
    public function export(Employee $actor, string $kind): array
    {
        abort_if(! in_array($kind, EduExport::KINDS, true), 422, 'EDU_EXPORT_KIND');

        $rows = match ($kind) {
            EduExport::KIND_STUDENTS => $this->students($actor),
            EduExport::KIND_PRESENCE => $this->presence($actor),
            EduExport::KIND_GRADES => $this->grades($actor),
        };

        $filename = sprintf('edu_%s_%s.csv', $kind, now()->format('Y-m-d'));

        /** @var EduExport $export */
        $export = EduExport::query()->create([
            'company_id' => $actor->company_id,
            'kind' => $kind,
            'filename' => $filename,
            'record_count' => count($rows),
            'exported_by' => $actor->id,
        ]);

        return [
            'filename' => $filename,
            'content' => $this->toCsv($rows),
            'count' => count($rows),
            'export_id' => (int) $export->getAttribute('id'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function students(Employee $actor): array
    {
        return EduStudent::query()
            ->where('company_id', $actor->company_id)
            ->orderBy('student_number')
            ->get()
            ->map(fn (EduStudent $student): array => [
                'student_number' => $student->student_number,
                'display_name' => $student->display_name,
                'status' => $student->status,
                'created_at' => $student->created_at?->toDateString(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function presence(Employee $actor): array
    {
        return EduAttendance::query()
            ->with('student:id,student_number,display_name')
            ->where('company_id', $actor->company_id)
            ->orderBy('attendance_date')
            ->get()
            ->map(fn (EduAttendance $attendance): array => [
                'student_number' => $attendance->student?->student_number,
                'attendance_date' => $attendance->attendance_date->toDateString(),
                'status' => $attendance->status,
                'reason' => $attendance->reason,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function grades(Employee $actor): array
    {
        return EduGrade::query()
            ->with('student:id,student_number,display_name')
            ->with('assessment:id,title,subject_id')
            ->where('company_id', $actor->company_id)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (EduGrade $grade): array => [
                'student_number' => $grade->student?->student_number,
                'assessment' => $grade->assessment?->title,
                'score' => $grade->score,
                'status' => $grade->status,
                'version' => (int) $grade->version,
            ])
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function toCsv(array $rows): string
    {
        if ($rows === []) {
            return '';
        }

        $headers = array_keys($rows[0]);
        $out = fopen('php://temp', 'r+');
        if ($out === false) {
            return '';
        }

        fputcsv($out, $headers);
        foreach ($rows as $row) {
            fputcsv($out, array_map(
                fn (mixed $value): string => Str::startsWith((string) $value, ['=', '+', '-', '@'])
                    ? "'".(string) $value
                    : (string) $value,
                array_values($row)
            ));
        }

        rewind($out);

        return (string) stream_get_contents($out);
    }
}
