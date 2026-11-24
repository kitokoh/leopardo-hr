<?php

declare(strict_types=1);

namespace App\AI\Support;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Payroll\Domain\Models\Payroll;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;

/**
 * Handlers de lecture Paie de l'assistant — extraits de `IntentEngine` par #9887.
 *
 * ## Pourquoi la paie est isolee des autres handlers RH
 *
 * Ce sont les deux seuls outils dont la sortie touche des montants. Les
 * separer rend la regle de confidentialite (privacy A6, #6853) verifiable
 * d'un coup d'oeil : **aucun handler de ce fichier ne renvoie de montant
 * nominatif ni de total**. `getPayrollSummary` renvoie les bulletins de
 * l'acteur lui-meme (il a le droit de voir sa fiche) ; `getPayrollCurrentStatus`
 * renvoie des statuts, des dates et des compteurs, jamais un montant.
 *
 * Melangees aux 19 autres handlers, ces deux regles etaient noyees dans
 * 1 375 lignes. Ici, une revue de securite peut les auditer seules.
 */
final class PayrollReadHandlers
{
    public function __construct(
        private readonly ToolArgumentReader $arguments,
    ) {}

    /**
     * Bulletins de l'acteur lui-meme — jamais ceux d'un autre employe.
     *
     * @return array<string, mixed>
     */
    public function getPayrollSummary(string $companyId, int $userId): array
    {
        $payrolls = Payroll::where('company_id', $companyId)
            ->where('employee_id', $userId)
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->limit(6)
            ->get(['id', 'period_month', 'period_year', 'gross_salary', 'net_salary', 'status', 'validated_at']);

        return ['payrolls' => $payrolls->toArray(), 'count' => $payrolls->count()];
    }

    /**
     * B2 (#6855) — statut agrege de la paie du tenant (BC-07 PAYROLL).
     *
     * Reutilise les modeles canoniques `PayrollRun`/`PaySlip` (contrat
     * PayrollRunController) : dernier run cloture (valide/paye/verrouille) et
     * run en cours eventuel (draft/calculating/processing/calculated/error)
     * avec progression — bulletins generes/valides rapportes a l'effectif du
     * run. Sortie AGREGEE : statuts, dates et compteurs uniquement — jamais de
     * montants (nominatifs ou totaux), jamais de bulletins (privacy A6, #6853).
     * Lecteur : manager RH/principal (matrice ai.tool_permissions, role
     * manager + payroll.view) — fail-closed sinon (#6532).
     *
     * @return array<string, mixed>
     */
    public function getPayrollCurrentStatus(string $companyId, int $userId): array
    {
        /** @var Employee|null $actor */
        $actor = Employee::where('company_id', $companyId)->find($userId);

        if ($actor === null) {
            return ['error' => 'Employee not found'];
        }

        // #6532 — defense en profondeur : un non-manager (jamais atteint via la
        // matrice ai.tool_permissions) n'accede pas au statut de paie.
        if (! $actor->isManager()) {
            return ['error' => 'Employee not found'];
        }

        $runs = PayrollRun::query()
            ->where('company_id', $companyId)
            ->where('status', '!=', PayrollRun::STATUS_CANCELLED)
            ->orderByDesc('period_end')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        $currentRun = null;
        $lastClosedRun = null;

        /** @var PayrollRun $run */
        foreach ($runs as $run) {
            if (in_array($run->status, [
                PayrollRun::STATUS_DRAFT,
                PayrollRun::STATUS_CALCULATING,
                PayrollRun::STATUS_PROCESSING,
                PayrollRun::STATUS_CALCULATED,
                PayrollRun::STATUS_ERROR,
            ], true) && $currentRun === null) {
                $currentRun = $run;
            }

            if (in_array($run->status, [
                PayrollRun::STATUS_VALIDATED,
                PayrollRun::STATUS_PAID,
                PayrollRun::STATUS_LOCKED,
            ], true) && $lastClosedRun === null) {
                $lastClosedRun = $run;
            }

            if ($currentRun !== null && $lastClosedRun !== null) {
                break;
            }
        }

        return [
            'has_current_run' => $currentRun !== null,
            'current_run' => $currentRun !== null ? $this->payrollRunStatusPayload($currentRun) : null,
            'last_closed_run' => $lastClosedRun !== null ? $this->payrollRunStatusPayload($lastClosedRun) : null,
            'as_of' => now()->toIso8601String(),
        ];
    }

    /**
     * Payload agrege d'un run : identite temporelle, statut, effectif et
     * compteurs de bulletins — explicitement AUCUN montant (privacy A6,
     * #6853). `validated` = bulletins au statut `validated` (scope canonique
     * PaySlip::scopeValidated).
     *
     * @return array<string, mixed>
     */
    public function payrollRunStatusPayload(PayrollRun $run): array
    {
        $slipsCount = PaySlip::query()->where('payroll_run_id', $run->id)->count();
        $validatedSlipsCount = PaySlip::query()
            ->where('payroll_run_id', $run->id)
            ->where('status', 'validated')
            ->count();

        return [
            'id' => $run->id,
            'status' => $run->status,
            'period' => [
                'start' => $run->period_start->toDateString(),
                'end' => $run->period_end->toDateString(),
            ],
            'employee_count' => $run->employee_count,
            'slips_count' => $slipsCount,
            'validated_slips_count' => $validatedSlipsCount,
            'calculated_at' => $run->calculated_at?->toIso8601String(),
            'validated_at' => $run->validated_at?->toIso8601String(),
            'paid_at' => $run->paid_at?->toIso8601String(),
            'updated_at' => $run->updated_at?->toIso8601String(),
        ];
    }
}
