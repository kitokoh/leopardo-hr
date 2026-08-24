<?php

declare(strict_types=1);

namespace App\Modules\HR\Application\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Domain\Models\User;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Events\CandidateHired;
use App\Modules\HR\Application\DTOs\CreateEmployeeDTO;
use App\Modules\HR\Domain\Exceptions\CandidateAlreadyHiredException;
use App\Modules\HR\Domain\Exceptions\OnboardingIncompleteException;
use App\Modules\HR\Domain\Models\OnboardingStep;
use App\Modules\HR\Infrastructure\Services\EmployeeService;
use App\Modules\Recruitment\Domain\Models\Applicant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Issue #5261 — fermer la boucle candidat → employé.
 *
 * À partir d'un Applicant (module Recruitment, lu en LECTURE SEULE —
 * périmètre HR strict), crée la fiche employé avec traçabilité
 * (`candidate_id`) via le `EmployeeService` existant, sous gardes :
 *  - anti-doublon : candidat déjà embauché (statut `hired` ou candidate_id
 *    déjà présent) → 422 ;
 *  - onboarding : les steps obligatoires de l'entreprise doivent être
 *    complétés avant l'activation (garde « onboarding → paie ») ;
 *  - premier run de paie possible dès l'embauche datée : `contract_start`
 *    est positionné (défaut = aujourd'hui dans EmployeeService), le
 *    PayrollCalculator proratise déjà sur cette date.
 */
class CandidateHiringService
{
    public function __construct(
        private readonly EmployeeService $employeeService,
    ) {}

    /**
     * @param  array<string, mixed>  $data  champs de surcharge (first_name, last_name,
     *                                      email, phone, contract_start, contract_type…)
     */
    public function hire(string $applicantId, array $data = [], Employee|User|SuperAdmin|null $actor = null): Employee
    {
        /** @var Applicant|null $applicant */
        $applicant = Applicant::query()->find($applicantId);

        if ($applicant === null) {
            throw new ModelNotFoundException(
                sprintf('Applicant %s introuvable.', $applicantId)
            );
        }

        // ── Garde anti-doublon (constitution §II) ─────────────────────────────
        if ($applicant->status === 'hired') {
            throw CandidateAlreadyHiredException::forCandidate($applicantId);
        }

        $already = Employee::query()
            ->where('candidate_id', $applicant->id)
            ->exists();

        if ($already) {
            throw CandidateAlreadyHiredException::forCandidate($applicantId);
        }

        // ── Garde onboarding : steps obligatoires avant activation ────────────
        $incomplete = OnboardingStep::query()
            ->where('company_id', $applicant->company_id)
            ->where('required', true)
            ->where('status', '!=', 'completed')
            ->exists();

        if ($incomplete) {
            throw OnboardingIncompleteException::forCompany((string) $applicant->company_id);
        }

        // ── Création de la fiche employé (EmployeeService, non modifié) ───────
        $dto = new CreateEmployeeDTO(
            first_name: (string) ($data['first_name'] ?? $applicant->first_name),
            last_name: (string) ($data['last_name'] ?? $applicant->last_name),
            email: (string) ($data['email'] ?? $applicant->email),
            phone: (string) ($data['phone'] ?? $applicant->phone),
            company_id: (string) $applicant->company_id,
            contract_type: isset($data['contract_type']) ? (string) $data['contract_type'] : null,
            contract_start: isset($data['contract_start']) ? (string) $data['contract_start'] : null,
            send_invitation: (bool) ($data['send_invitation'] ?? false),
        );

        // Atomicité : fiche employé + traçabilité + événement dans la même
        // transaction (EmployeeService::create émet déjà EmployeeCreated).
        return DB::transaction(function () use ($applicant, $dto, $actor): Employee {
            $employee = $this->employeeService->create($dto, $actor);

            // Traçabilité candidat → employé (colonne additive, migration #5261)
            $employee->forceFill(['candidate_id' => $applicant->id])->save();

            event(new CandidateHired($applicant, $employee));

            return $employee;
        });
    }

    /**
     * Un candidat embauché devient immédiatement éligible à la paie :
     * `contract_start` est posé (défaut aujourd'hui) et le calculateur
     * proratise sur la date d'embauche.
     */
    public function assertPayrollEligible(Employee $employee): bool
    {
        return $employee->contract_start !== null;
    }
}
