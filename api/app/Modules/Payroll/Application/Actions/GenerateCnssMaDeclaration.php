<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Infrastructure\Services\SocialDeclarationGenerator;
use App\Modules\Payroll\Infrastructure\Services\SocialDeclarationService;
use Illuminate\Database\ConnectionInterface;

/**
 * Cas d'usage : declaration trimestrielle CNSS (MA) - une ligne par employe
 * actif du trimestre (n° CNSS, identite, brut, jours travailles deduits des
 * logs de presence), agregee depuis les bulletins (route .../generate-cnss-ma).
 *
 * Orchestration pure et nommable (ADR-0020, lot 3b declarations - #6968) :
 * la collecte (employes actifs, mois du trimestre, donnees de paie, jours de
 * presence) et le formatage restent dans `SocialDeclarationService` /
 * `SocialDeclarationGenerator` (Infrastructure). L'Action retourne le contenu
 * prêt à servir ; l'interface (controleur) conserve l'autorisation, la
 * validation et l'enveloppe HTTP.
 *
 * @return array{content: string, employee_count: int, filename: string}
 */
class GenerateCnssMaDeclaration
{
    public function __construct(
        private readonly SocialDeclarationService $declarations,
        private readonly ConnectionInterface $db,
    ) {}

    /**
     * @return array{content: string, employee_count: int, filename: string}
     */
    public function execute(Employee $actor, string $quarter, int $year): array
    {
        $employees = $this->declarations->activeEmployees((string) $actor->company_id);

        $quarterMonths = $this->declarations->quarterMonths($quarter);

        /** @var \Illuminate\Support\Collection<int, object{total_gross?: int|float|string|null}> $payrollData */
        $payrollData = $this->declarations->quarterPayrollData(
            (string) $actor->company_id,
            $year,
            $quarterMonths,
        );

        /** @var \Illuminate\Support\Collection<int, object{days_worked?: int|string|null}> $attendanceData */
        $attendanceData = $this->db->table('attendance_logs')
            ->where('company_id', $actor->company_id)
            ->whereYear('check_in', $year)
            ->whereIn($this->db->raw('EXTRACT(MONTH FROM check_in)'), $quarterMonths)
            ->select([
                'employee_id',
                $this->db->raw('COUNT(DISTINCT DATE(check_in)) as days_worked'),
            ])
            ->groupBy('employee_id')
            ->get()
            ->keyBy('employee_id');

        $company = Company::query()->whereKey($actor->company_id)->first();

        $companyName = $company->name ?? 'N/A';
        $companyAffiliate = $this->companyRegistrationNumber($company);

        $declarationRows = $employees->map(function ($emp) use ($payrollData, $attendanceData): array {
            $payroll = $payrollData->get($emp->id);
            $attendance = $attendanceData->get($emp->id);

            /** @var array{employee_id: int, num_cnss: string, last_name: string, first_name: string, cin: string, gross_salary: float, days_worked: int} $row */
            $row = [
                'employee_id' => (int) $emp->id,
                'num_cnss' => (string) ($emp->national_id ?? ''),
                'last_name' => (string) ($emp->last_name ?? ''),
                'first_name' => (string) ($emp->first_name ?? ''),
                'cin' => '',
                'gross_salary' => (float) ($payroll->total_gross ?? 0),
                'days_worked' => (int) ($attendance->days_worked ?? 0),
            ];

            return $row;
        })->filter(fn (array $row): bool => $row['gross_salary'] > 0);

        // Le `filter()` ci-dessus retrecit `gross_salary` a une plage strictement
        // positive (`float<5.0E-324, max>`). Le template `TValue` de `Collection`
        // n'est pas covariant : elargir explicitement au contrat declare du
        // generateur, qui attend un `float` ordinaire (PHPStan 2.3).
        /** @var \Illuminate\Support\Collection<int, array{employee_id: int, num_cnss: string, last_name: string, first_name: string, cin: string, gross_salary: float, days_worked: int}> $declarationRows */
        $generator = new SocialDeclarationGenerator;
        $content = $generator->generateCnssMa(
            $companyName,
            $companyAffiliate,
            $quarter,
            $year,
            $declarationRows->values(),
        );

        /** @var array{content: string, employee_count: int, filename: string} $result */
        $result = [
            'content' => $content,
            'employee_count' => $declarationRows->count(),
            'filename' => sprintf('CNSS_MA_%s_%d_%s.txt', $quarter, $year, now()->format('Ymd')),
        ];

        return $result;
    }

    private function companyRegistrationNumber(?Company $company): string
    {
        if ($company === null) {
            return '';
        }

        $candidate = $company->metadata['tax_id']
            ?? $company->metadata['nis']
            ?? $company->metadata['affiliate_number']
            ?? $company->metadata['siret']
            ?? '';

        return is_scalar($candidate) ? (string) $candidate : '';
    }
}
