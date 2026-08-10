<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\DataAccessAuditLogger;
use App\Http\Controllers\Controller;
use App\Modules\Payroll\Infrastructure\Services\EndOfContractService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Programme FOCUS — F-08 (#1538) : fin de contrat.
 *
 *  - GET /employees/{employee}/end-of-contract          → solde de tout compte (JSON)
 *  - GET /employees/{employee}/certificate-of-employment → certificat de travail (PDF)
 *
 * Réservé aux managers (principal/RH/comptable), isolé par tenant.
 * Les lectures sont tracées dans `audit_logs` (Spec S-2, #1662).
 */
class EndOfContractController extends Controller
{
    public function __construct(
        private readonly EndOfContractService $service,
        private readonly DataAccessAuditLogger $dataAccessAuditLogger,
    ) {}

    public function settlement(Request $request, Employee $employee): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($employee->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($actor->isManager() === false) {
            abort(403);
        }

        $this->dataAccessAuditLogger->recordSensitive($request, $actor, 'hr_data.end_of_contract_viewed', $employee, [
            'resource' => 'end_of_contract',
            'target_employee_id' => $employee->id,
        ]);

        return response()->json([
            'data' => $this->service->settlement($employee),
        ]);
    }

    public function certificate(Request $request, Employee $employee): Response
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($employee->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($actor->isManager() === false) {
            abort(403);
        }

        $this->dataAccessAuditLogger->recordSensitive($request, $actor, 'hr_data.certificate_downloaded', $employee, [
            'resource' => 'certificate_of_employment',
            'target_employee_id' => $employee->id,
        ]);

        $data = $this->service->certificateData($employee);
        $pdf = Pdf::loadView('pdf.certificate_of_employment', $data);
        $pdf->setPaper('A4', 'portrait');

        return $pdf->download('certificat_travail_'.$employee->id.'.pdf');
    }
}
