<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use Illuminate\Support\Collection;

/**
 * #5243 — Bordereau de paie d'un run : récapitulatif CSV avec (1) les totaux
 * par cotisation (lignes de bulletin groupées par type + libellé) et (2) le
 * récapitulatif du run (brut, cotisations salariales, IRG, autres déductions,
 * net, cotisations patronales, coût employeur, nombre de bulletins).
 *
 * Utilisé par `GET /api/v1/payroll-runs/{run}/bordereau` (garde pays DZ).
 * Seuls les bulletins `validated` sont comptés (même règle que le journal de
 * paie et les déclarations sociales).
 */
class PayrollBordereauGenerator
{
    public function generate(PayrollRun $run): string
    {
        $slips = $run->paySlips()
            ->with('lines')
            ->where('status', 'validated')
            ->get();

        $lines = [];
        $lines[] = $this->row(['BORDEREAU', (string) $run->id, $run->period_start->toDateString(), $run->period_end->toDateString(), (string) $run->country_code]);

        // Section 1 — totaux par cotisation (type, libellé, nb lignes, total).
        $lines[] = $this->row(['SECTION', 'TOTAUX_PAR_COTISATION']);
        foreach ($this->totalsByContribution($slips) as $contribution) {
            $lines[] = $this->row([
                'COTISATION',
                $contribution['type'],
                $contribution['name'],
                (string) $contribution['count'],
                number_format($contribution['total'], 2, '.', ''),
            ]);
        }

        // Section 2 — récapitulatif du run.
        $lines[] = $this->row(['SECTION', 'RECAPITULATIF_RUN']);
        foreach ($this->runTotals($slips) as $label => $value) {
            $lines[] = $this->row(['RECAP', $label, number_format($value, 2, '.', '')]);
        }

        return implode("\r\n", $lines)."\r\n";
    }

    /**
     * @param  Collection<int, PaySlip>  $slips
     * @return list<array{type: string, name: string, count: int, total: float}>
     */
    private function totalsByContribution(Collection $slips): array
    {
        $aggregate = [];

        foreach ($slips as $slip) {
            foreach ($slip->lines as $line) {
                $type = (string) ($line->type ?? 'other');
                $name = (string) ($line->name ?? '');
                $key = $type."\0".$name;

                if (! isset($aggregate[$key])) {
                    $aggregate[$key] = ['type' => $type, 'name' => $name, 'count' => 0, 'total' => 0.0];
                }

                $aggregate[$key]['count']++;
                $aggregate[$key]['total'] += (float) $line->amount;
            }
        }

        uasort($aggregate, static fn (array $a, array $b): int => [$a['type'], $a['name']] <=> [$b['type'], $b['name']]);

        return array_values($aggregate);
    }

    /**
     * @param  Collection<int, PaySlip>  $slips
     * @return array<string, float>
     */
    private function runTotals(Collection $slips): array
    {
        $gross = (float) $slips->sum('gross_salary');
        $net = (float) $slips->sum('net_salary');
        $employer = (float) $slips->sum('employer_contributions');
        $cost = (float) $slips->sum('total_cost');

        $cotisations = 0.0;
        $irg = 0.0;
        foreach ($slips as $slip) {
            foreach ($slip->lines as $line) {
                if ((string) $line->type !== 'deduction') {
                    continue;
                }
                if ((string) $line->name === 'Cotisations salariales') {
                    $cotisations += (float) $line->amount;
                } else {
                    $irg += (float) $line->amount;
                }
            }
        }

        $deductions = (float) $slips->sum('total_deductions');

        return [
            'brut_total' => $gross,
            'cotisations_salariales' => $cotisations,
            'irg' => $irg,
            'autres_deductions' => max(0.0, $deductions - $cotisations - $irg),
            'net_total' => $net,
            'cotisations_patronales' => $employer,
            'cout_employeur' => $cost,
            'bulletins' => (float) $slips->count(),
        ];
    }

    /**
     * @param  list<int|float|string>  $cells
     */
    private function row(array $cells): string
    {
        return implode(';', array_map(static function (int|float|string $cell): string {
            $cell = (string) $cell;

            return '"'.str_replace('"', '""', $cell).'"';
        }, $cells));
    }
}
