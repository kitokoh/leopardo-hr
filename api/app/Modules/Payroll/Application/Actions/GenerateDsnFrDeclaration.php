<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Infrastructure\Services\SocialDeclarationGenerator;
use App\Modules\Payroll\Infrastructure\Services\SocialDeclarationService;
use DateTimeInterface;

/**
 * Cas d'usage : DSN mensuelle (FR) - une ligne par employe actif du mois
 * (NIR, identite, date de naissance, brut/net/net imposable, heures,
 * contrat), agregee depuis les bulletins (route .../generate-dsn-fr).
 *
 * Orchestration pure et nommable (ADR-0020, lot 3b declarations - #6968) :
 * la collecte (employes actifs, donnees de paie mensuelles) et le formatage
 * restent dans `SocialDeclarationService` / `SocialDeclarationGenerator`
 * (Infrastructure). L'Action retourne le contenu prêt à servir ; l'interface
 * (controleur) conserve l'autorisation, la validation et l'enveloppe HTTP.
 *
 * @return array{content: string, employee_count: int, filename: string}
 */
class GenerateDsnFrDeclaration
{
    public function __construct(
        private readonly SocialDeclarationService $declarations,
    ) {}

    /**
     * @return array{content: string, employee_count: int, filename: string}
     */
    public function execute(Employee $actor, int $month, int $year): array
    {
        $employees = $this->declarations->activeEmployees((string) $actor->company_id);

        /** @var \Illuminate\Support\Collection<int, object{total_gross?: int|float|string|null, total_net?: int|float|string|null}> $payrollData */
        $payrollData = $this->declarations->monthPayrollData(
            (string) $actor->company_id,
            $year,
            $month,
        );

        $company = Company::query()->whereKey($actor->company_id)->first();

        $companyName = $company->name ?? 'N/A';
        $companySiret = $this->companyRegistrationNumber($company);

        $declarationRows = $employees->map(function ($emp) use ($payrollData): array {
            $payroll = $payrollData->get($emp->id);

            /** @var array{employee_id: int, nir: string, last_name: string, first_name: string, date_naissance: string, gross_salary: float, net_salary: float, net_imposable?: float, hours_worked?: float, contract_type: string, start_date: string} $row */
            $row = [
                'employee_id' => (int) $emp->id,
                'nir' => (string) ($emp->national_id ?? ''),
                'last_name' => (string) ($emp->last_name ?? ''),
                'first_name' => (string) ($emp->first_name ?? ''),
                'date_naissance' => $this->dateValue($emp->date_of_birth ?? null),
                'gross_salary' => (float) ($payroll->total_gross ?? 0),
                'net_salary' => (float) ($payroll->total_net ?? 0),
                'net_imposable' => (float) ($payroll->total_net ?? 0),
                'hours_worked' => 151.67,
                'contract_type' => (string) ($emp->contract_type ?? 'CDI'),
                'start_date' => $this->dateValue($emp->contract_start ?? null),
            ];

            return $row;
        })->filter(fn (array $row): bool => $row['gross_salary'] > 0);

        // Le `filter()` ci-dessus retrecit `gross_salary` a une plage strictement
        // positive (`float<5.0E-324, max>`). Le template `TValue` de `Collection`
        // n'est pas covariant : elargir explicitement au contrat declare du
        // generateur, qui attend un `float` ordinaire (PHPStan 2.3).
        /** @var \Illuminate\Support\Collection<int, array{employee_id: int, nir: string, last_name: string, first_name: string, date_naissance: string, gross_salary: float, net_salary: float, net_imposable?: float, hours_worked?: float, contract_type: string, start_date: string}> $declarationRows */
        $generator = new SocialDeclarationGenerator;
        $content = $generator->generateDsnFr(
            $companyName,
            $companySiret,
            str_pad((string) $month, 2, '0', STR_PAD_LEFT),
            $year,
            $declarationRows->values(),
        );

        /** @var array{content: string, employee_count: int, filename: string} $result */
        $result = [
            'content' => $content,
            'employee_count' => $declarationRows->count(),
            'filename' => sprintf('DSN_FR_%02d_%d_%s.dsn', $month, $year, now()->format('Ymd')),
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
