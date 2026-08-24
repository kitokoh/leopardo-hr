<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\HR\Application\Services\CandidateHiringService;
use App\Modules\HR\Domain\Exceptions\CandidateAlreadyHiredException;
use App\Modules\HR\Domain\Exceptions\OnboardingIncompleteException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Issue #5261 — embauche d'un candidat (Recruitment → HR).
 *
 * POST /api/v1/hr/candidates/{applicant}/hire
 * Crée la fiche employé avec traçabilité candidate_id, sous gardes
 * anti-doublon et onboarding obligatoire.
 */
class CandidateHiringController extends Controller
{
    public function __construct(
        private readonly CandidateHiringService $candidateHiringService,
    ) {}

    public function store(Request $request, string $applicant): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:50'],
            'contract_type' => ['sometimes', 'string', 'max:50'],
            'contract_start' => ['sometimes', 'date'],
            'send_invitation' => ['sometimes', 'boolean'],
        ]);

        try {
            /** @var Employee $employee */
            $employee = $this->candidateHiringService->hire(
                $applicant,
                $data,
                $request->user()
            );
        } catch (CandidateAlreadyHiredException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (OnboardingIncompleteException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json([
            'data' => [
                'id' => $employee->id,
                'candidate_id' => $employee->getAttribute('candidate_id'),
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'email' => $employee->email,
                'contract_start' => $employee->contract_start?->toDateString(),
                'payroll_eligible' => $this->candidateHiringService->assertPayrollEligible($employee),
            ],
        ], 201);
    }
}
