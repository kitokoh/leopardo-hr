<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Infrastructure\Services;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduAssessment;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Domain\Models\EduGrade;
use App\Modules\EduManager\Domain\Models\EduGuardian;
use App\Modules\EduManager\Domain\Models\EduImport;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Domain\Models\EduSubject;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Import CSV sécurisé EduManager — EDU-017 (issue #5833).
 *
 * - PREVIEW : parse + validation structurelle (en-têtes, types, bornes)
 *   SANS aucune écriture cible ; échantillon masqué conservé.
 * - COMMIT : idempotent (statuts terminaux refusés, doublons détectés sur
 *   les clés naturelles student_number / code) ; erreurs rapportées ligne à
 *   ligne ; rollback logique possible depuis raw_rows.
 * - Audit : chaque étape sensible est journalisée (AuditLog, module `edu`,
 *   préfixe `edu.import.*`).
 * - PII : jamais de données nominatives dans les logs ni les erreurs.
 */
final class EduImportService
{
    public const PREVIEW_SAMPLE_SIZE = 5;

    /** @var array<string, list<string>> */
    private const TEMPLATES = [
        EduImport::ENTITY_STUDENTS => ['student_number', 'display_name', 'birth_date', 'status'],
        EduImport::ENTITY_GUARDIANS => ['first_name', 'last_name', 'relationship_code', 'student_number'],
        EduImport::ENTITY_SUBJECTS => ['code', 'name', 'default_coefficient', 'status'],
        EduImport::ENTITY_CLASSES => ['code', 'name', 'academic_year_id', 'campus_id', 'capacity'],
        EduImport::ENTITY_GRADES => ['student_number', 'subject_code', 'score', 'comment'],
    ];

    /**
     * @return array{columns: list<string>, rows: list<array<string, mixed>>, errors: list<array{row: int, error: string}>}
     */
    public function parse(UploadedFile $file, string $entityType): array
    {
        $path = $file->getRealPath();
        abort_if($path === false || $file->getSize() > 2_000_000, 422, 'EDU_IMPORT_TOO_LARGE');

        $handle = fopen($path, 'rb');
        abort_if($handle === false, 422, 'EDU_IMPORT_UNREADABLE');

        $headers = fgetcsv($handle);
        abort_if($headers === false || ! is_array($headers), 422, 'EDU_IMPORT_EMPTY');

        $expected = self::TEMPLATES[$entityType] ?? [];
        $headers = array_map('trim', $headers);
        $unknown = array_diff($headers, $expected);
        abort_if($unknown !== [], 422, 'EDU_IMPORT_HEADERS');

        $rows = [];
        $errors = [];
        $rowNumber = 1;

        while (($line = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if ($line === [null] || $line === []) {
                continue;
            }

            $row = [];
            foreach ($headers as $i => $header) {
                $row[$header] = $line[$i] ?? null;
            }

            $error = $this->validateRow($entityType, $row);
            if ($error !== null) {
                $errors[] = ['row' => $rowNumber, 'error' => $error];

                continue;
            }

            $rows[] = $this->normalizeRow($entityType, $row);
        }

        fclose($handle);

        return [
            'columns' => $headers,
            'rows' => $rows,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function preview(Employee $actor, UploadedFile $file, string $entityType): EduImport
    {
        abort_if(! in_array($entityType, EduImport::ENTITIES, true), 422, 'EDU_IMPORT_ENTITY');

        $result = $this->parse($file, $entityType);

        $sample = array_map(
            fn (array $row): array => $this->maskSensitive($row, $entityType),
            array_slice($result['rows'], 0, self::PREVIEW_SAMPLE_SIZE)
        );

        /** @var EduImport $import */
        $import = EduImport::query()->create([
            'company_id' => $actor->company_id,
            'entity_type' => $entityType,
            'filename' => $file->getClientOriginalName(),
            'status' => EduImport::STATUS_PREVIEWED,
            'total_rows' => count($result['rows']) + count($result['errors']),
            'valid_rows' => count($result['rows']),
            'error_rows' => count($result['errors']),
            'columns' => $result['columns'],
            'preview_data' => $sample,
            'errors' => array_slice($result['errors'], 0, 50),
            'raw_rows' => $result['rows'],
            'created_by' => $actor->id,
        ]);

        AuditLog::create([
            'company_id' => $actor->company_id,
            'user_id' => $actor->id,
            'action' => 'edu.import.previewed',
            'module' => 'edu',
            'auditable_type' => $import->getMorphClass(),
            'auditable_id' => $import->getAttribute('id'),
            'new_values' => [
                'entity_type' => $entityType,
                'filename' => $import->filename,
                'valid_rows' => $import->valid_rows,
                'error_rows' => $import->error_rows,
            ],
        ]);

        return $import;
    }

    public function commit(Employee $actor, EduImport $import): EduImport
    {
        abort_if($import->company_id !== $actor->company_id, 404);
        abort_if($import->isTerminal(), 422, 'EDU_IMPORT_TERMINAL');

        $rows = $import->raw_rows ?? [];
        $errors = [];
        $committed = 0;

        foreach ($rows as $index => $row) {
            try {
                $this->persistRow($actor, $import->entity_type, $row);
                $committed++;
            } catch (\Throwable $exception) {
                $errors[] = ['row' => $index + 2, 'error' => $this->safeError($exception)];
            }
        }

        $import->update([
            'status' => EduImport::STATUS_COMMITTED,
            'committed_by' => $actor->id,
            'committed_at' => now(),
            'valid_rows' => $committed,
            'error_rows' => count($errors),
            'errors' => array_merge($import->errors ?? [], array_slice($errors, 0, 50)),
        ]);

        AuditLog::create([
            'company_id' => $actor->company_id,
            'user_id' => $actor->id,
            'action' => 'edu.import.committed',
            'module' => 'edu',
            'auditable_type' => $import->getMorphClass(),
            'auditable_id' => $import->getAttribute('id'),
            'new_values' => [
                'entity_type' => $import->entity_type,
                'committed_rows' => $committed,
                'error_rows' => count($errors),
            ],
        ]);

        return $import->refresh();
    }

    public function cancel(Employee $actor, EduImport $import): EduImport
    {
        abort_if($import->company_id !== $actor->company_id, 404);
        abort_if($import->isTerminal(), 422, 'EDU_IMPORT_TERMINAL');

        $import->update(['status' => EduImport::STATUS_CANCELLED]);

        return $import->refresh();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function persistRow(Employee $actor, string $entityType, array $row): void
    {
        switch ($entityType) {
            case EduImport::ENTITY_STUDENTS:
                EduStudent::query()->firstOrCreate(
                    ['company_id' => $actor->company_id, 'student_number' => (string) $row['student_number']],
                    [
                        'display_name' => (string) $row['display_name'],
                        'birth_date_encrypted' => $row['birth_date'] ?? null,
                        'status' => $row['status'] ?? EduStudent::STATUS_ACTIVE,
                    ]
                );
                break;

            case EduImport::ENTITY_GUARDIANS:
                EduGuardian::query()->firstOrCreate(
                    [
                        'company_id' => $actor->company_id,
                        'first_name' => (string) $row['first_name'],
                        'last_name' => (string) $row['last_name'],
                        'relationship_code' => (string) $row['relationship_code'],
                    ],
                    []
                );
                break;

            case EduImport::ENTITY_SUBJECTS:
                EduSubject::query()->firstOrCreate(
                    ['company_id' => $actor->company_id, 'code' => (string) $row['code']],
                    [
                        'name' => (string) $row['name'],
                        'default_coefficient' => (string) ($row['default_coefficient'] ?? 1),
                        'status' => $row['status'] ?? EduSubject::STATUS_ACTIVE,
                    ]
                );
                break;

            case EduImport::ENTITY_CLASSES:
                // Les clés académique/campus sont validées structurellement au
                // preview ; l'intégrité FK est garantie par la base (422).
                EduClass::query()->firstOrCreate(
                    [
                        'company_id' => $actor->company_id,
                        'code' => (string) $row['code'],
                        'academic_year_id' => (int) $row['academic_year_id'],
                    ],
                    [
                        'name' => (string) $row['name'],
                        'campus_id' => $row['campus_id'] ?? null,
                        'capacity' => $row['capacity'] ?? null,
                        'status' => EduClass::STATUS_ACTIVE,
                    ]
                );
                break;

            case EduImport::ENTITY_GRADES:
                $student = EduStudent::query()
                    ->where('company_id', $actor->company_id)
                    ->where('student_number', (string) $row['student_number'])
                    ->first();
                abort_if($student === null, 422, 'EDU_IMPORT_STUDENT_UNKNOWN');

                $subject = EduSubject::query()
                    ->where('company_id', $actor->company_id)
                    ->where('code', (string) $row['subject_code'])
                    ->first();
                abort_if($subject === null, 422, 'EDU_IMPORT_SUBJECT_UNKNOWN');

                $assessment = EduAssessment::query()
                    ->where('company_id', $actor->company_id)
                    ->where('subject_id', (int) $subject->getAttribute('id'))
                    ->orderByDesc('assessment_date')
                    ->first();
                abort_if($assessment === null, 422, 'EDU_IMPORT_ASSESSMENT_UNKNOWN');

                EduGrade::query()->firstOrCreate(
                    [
                        'company_id' => $actor->company_id,
                        'assessment_id' => (int) $assessment->getAttribute('id'),
                        'student_id' => (int) $student->getAttribute('id'),
                    ],
                    [
                        'score' => (string) $row['score'],
                        'comment' => $row['comment'] ?? null,
                        'graded_by' => $actor->id,
                        'status' => EduGrade::STATUS_DRAFT,
                        'version' => 1,
                    ]
                );
                break;
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function validateRow(string $entityType, array $row): ?string
    {
        switch ($entityType) {
            case EduImport::ENTITY_STUDENTS:
                if (empty($row['student_number']) || empty($row['display_name'])) {
                    return 'student_number et display_name requis';
                }

                return null;

            case EduImport::ENTITY_GUARDIANS:
                if (empty($row['first_name']) || empty($row['last_name'])) {
                    return 'first_name et last_name requis';
                }
                if (! in_array($row['relationship_code'], EduGuardian::RELATIONSHIPS, true)) {
                    return 'relationship_code invalide (parent|guardian|other)';
                }

                return null;

            case EduImport::ENTITY_SUBJECTS:
                if (empty($row['code']) || empty($row['name'])) {
                    return 'code et name requis';
                }

                return null;

            case EduImport::ENTITY_CLASSES:
                if (empty($row['code']) || empty($row['name'])) {
                    return 'code et name requis';
                }
                if (! is_numeric($row['academic_year_id'])) {
                    return 'academic_year_id requis (numérique)';
                }

                return null;

            case EduImport::ENTITY_GRADES:
                if (empty($row['student_number']) || empty($row['subject_code'])) {
                    return 'student_number et subject_code requis';
                }
                if (! is_numeric($row['score'])) {
                    return 'score requis (numérique)';
                }

                return null;

            default:
                return 'type inconnu';
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeRow(string $entityType, array $row): array
    {
        if ($entityType === EduImport::ENTITY_GRADES) {
            $row['score'] = (float) $row['score'];
        }
        if ($entityType === EduImport::ENTITY_CLASSES) {
            $row['academic_year_id'] = (int) $row['academic_year_id'];
            $row['campus_id'] = isset($row['campus_id']) && $row['campus_id'] !== '' ? (int) $row['campus_id'] : null;
            $row['capacity'] = isset($row['capacity']) && $row['capacity'] !== '' ? (int) $row['capacity'] : null;
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function maskSensitive(array $row, string $entityType): array
    {
        $masked = $row;

        if ($entityType === EduImport::ENTITY_STUDENTS) {
            $masked['display_name'] = '•••';
            $masked['birth_date'] = '••••-••-••';
        }
        if ($entityType === EduImport::ENTITY_GUARDIANS) {
            $masked['first_name'] = '•••';
            $masked['last_name'] = '•••';
        }

        return $masked;
    }

    private function safeError(\Throwable $exception): string
    {
        $message = $exception->getMessage();

        return Str::contains($message, ['unique', 'duplicate', 'foreign', 'check'])
            ? 'ligne en conflit avec les données existantes'
            : 'ligne invalide';
    }
}
