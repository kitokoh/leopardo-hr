<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Infrastructure\Services\SocialDeclarationGenerator;
use App\Modules\Payroll\Infrastructure\Services\SocialDeclarationService;
use DateTimeInterface;

/**
 * Cas d'usage : generation de la declaration trimestrielle CNAS (DZ) -
 * une ligne par employe actif du trimestre (NIS, identite, brut, mois
 * travailles), agregee depuis les bulletins (route .../generate-cnas).
 *
 * Orchestration pure et nommable (ADR-0020, lot 3a declarations - #6968) :
 * la collecte des donnees (employes actifs, mois du trimestre, donnees de
 * paie agregees) reste dans `SocialDeclarationService`, le formatage du
 * fichier dans `SocialDeclarationGenerator` (Infrastructure). L'Action
 * construit les lignes et retourne le contenu prêt à servir — l'interface
 * (controleur) conserve l'autorisation, la validation et l'enveloppe HTTP.
 *
 * @return array{content: string, employee_count: int, filename: string}
 */
class GenerateCnasDzDeclaration
{
    public function __construct(
        private readonly SocialDeclarationService $declarations,
    ) {}

    /**
     * @return array{content: string, employee_count: int, filename: string}
     */
    public function execute(Employee $actor, string $quarter, int $year): array
    {
        $employees = $this->declarations->activeEmployees((string) $actor->company_id);

        $quarterMonths = $this->declarations->quarterMonths($quarter);

        /** @var \Illuminate\Support\Collection<int, object{total_gross?: int|float|string|null, months_worked?: int|string|null}> $payrollData */
        $payrollData = $this->declarations->quarterPayrollData(
            (string) $actor->company_id,
            $year,
            $quarterMonths,
            withMonthsCount: true,
        );

        $company = Company::query()->whereKey($actor->company_id)->first();

        $companyName = $company->name ?? 'N/A';
        $companyNis = $this->companyRegistrationNumber($company);

        $declarationRows = $employees->map(function ($emp) use ($payrollData): array {
            $payroll = $payrollData->get($emp->id);

            /** @var array{employee_id: int, num_ss: string, last_name: string, first_name: string, date_naissance: string, gross_salary: float, months_worked: int} $row */
            $row = [
                'employee_id' => (int) $emp->id,
                'num_ss' => (string) ($emp->national_id ?? ''),
                'last_name' => (string) ($emp->last_name ?? ''),
                'first_name' => (string) ($emp->first_name ?? ''),
                'date_naissance' => $this->dateValue($emp->date_of_birth ?? null),
                'gross_salary' => (float) ($payroll->total_gross ?? 0),
                'months_worked' => (int) ($payroll->months_worked ?? 0),
            ];

            return $row;
        })->filter(fn (array $row): bool => $row['gross_salary'] > 0);

        // Le `filter()` ci-dessus retrecit `gross_salary` a une plage strictement
        // positive (`float<5.0E-324, max>`). Le template `TValue` de `Collection`
        // n'est pas covariant : elargir explicitement au contrat declare du
        // generateur, qui attend un `float` ordinaire (PHPStan 2.3).
        /** @var \Illuminate\Support\Collection<int, array{employee_id: int, num_ss: string, last_name: string, first_name: string, date_naissance: string, gross_salary: float, months_worked: int}> $declarationRows */
        $generator = new SocialDeclarationGenerator;
        $content = $generator->generateCnasDz(
            $companyName,
            $companyNis,
            $quarter,
            $year,
            $declarationRows->values(),
        );

        /** @var array{content: string, employee_count: int, filename: string} $result */
        $result = [
            'content' => $content,
            'employee_count' => $declarationRows->count(),
            'filename' => sprintf('CNAS_DZ_%s_%d_%s.txt', $quarter, $year, now()->format('Ymd')),
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

    private function dateValue(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return is_scalar($value) ? (string) $value : '';
    }
}
