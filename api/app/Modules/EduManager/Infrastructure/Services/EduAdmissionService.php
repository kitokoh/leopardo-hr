<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduAdmission;
use App\Modules\EduManager\Domain\Models\EduStudent;
use Illuminate\Support\Str;

/**
 * Règles métier des admissions EduManager — EDU-004 (issue #5820).
 *
 * - Idempotence : un `external_id` déjà enregistré (même tenant) renvoie le
 *   dossier existant (200) au lieu d'un doublon (rejeu sûr des webhooks).
 * - Consentement : un dossier ne peut être converti en élève sans
 *   `consent_contact` explicite (EDU_CONSENT_REQUIRED).
 * - Conversion : idempotente — un dossier déjà converti renvoie son élève ;
 *   la conversion crée l'élève avec le numéro du dossier si absent, pose
 *   `student_id` + `converted_at` et passe le statut à `converted`.
 * - Lien CRM client : `crm_contact_id` est une simple référence de contrat
 *   (jamais de FK, jamais d'import CRM commercial plateforme).
 * - Statuts terminaux (converted/cancelled) : aucune transition.
 */
final class EduAdmissionService
{
    /**
     * Création idempotente d'un dossier d'admission.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(Employee $actor, array $data): EduAdmission
    {
        $payload = array_merge($data, [
            'company_id' => $actor->company_id,
            'created_by' => $actor->id,
        ]);

        if (! empty($data['external_id'])) {
            $existing = EduAdmission::query()
                ->where('company_id', $actor->company_id)
                ->where('external_id', $data['external_id'])
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $payload['admission_number'] ??= $this->nextAdmissionNumber($actor);

        /** @var EduAdmission $admission */
        $admission = EduAdmission::query()->create($payload);

        return $admission;
    }

    /**
     * Conversion idempotente d'un dossier en élève (EDU-004).
     *
     * @param  array<string, mixed>  $data  surcharge optionnelle (campus_id…)
     */
    public function convertToStudent(Employee $actor, EduAdmission $admission, array $data = []): EduStudent
    {
        abort_if($admission->company_id !== $actor->company_id, 404);

        if ($admission->student_id !== null) {
            /** @var EduStudent $student */
            $student = EduStudent::query()->findOrFail($admission->student_id);

            return $student;
        }

        abort_if(! $admission->consent_contact, 422, 'EDU_CONSENT_REQUIRED');
        abort_if($admission->isTerminal(), 422, 'EDU_ADMISSION_TERMINAL');

        $studentNumber = $admission->admission_number;

        // Génère un numéro d'élève déterministe si déjà utilisé (rejeu).
        if (EduStudent::query()->where('company_id', $actor->company_id)
            ->where('student_number', $studentNumber)->exists()
        ) {
            $studentNumber = 'STU-'.Str::upper(Str::random(8));
        }

        /** @var EduStudent $student */
        $student = EduStudent::query()->create([
            'company_id' => $actor->company_id,
            'student_number' => $studentNumber,
            'display_name' => trim($admission->applicant_first_name.' '.$admission->applicant_last_name),
            'birth_date_encrypted' => $admission->applicant_birth_date?->format('Y-m-d'),
            'metadata' => array_merge([
                'source' => $admission->source,
                'admission_id' => (int) $admission->getAttribute('id'),
            ], $data['metadata'] ?? []),
            'status' => EduStudent::STATUS_ACTIVE,
        ]);

        $admission->update([
            'student_id' => (int) $student->getAttribute('id'),
            'converted_at' => now(),
            'status' => EduAdmission::STATUS_CONVERTED,
        ]);

        return $student;
    }

    private function nextAdmissionNumber(Employee $actor): string
    {
        $year = (string) now()->format('Y');
        $count = EduAdmission::query()
            ->where('company_id', $actor->company_id)
            ->count();

        do {
            $count++;
            $candidate = 'ADM-'.$year.'-'.str_pad((string) $count, 5, '0', STR_PAD_LEFT);
        } while (EduAdmission::query()
            ->where('company_id', $actor->company_id)
            ->where('admission_number', $candidate)
            ->exists());

        return $candidate;
    }
}
