<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Models\BankStatement;
use App\Modules\Accounting\Infrastructure\Services\BankReconciliationService;
use App\Modules\Accounting\Infrastructure\Services\BankStatementImportService;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Rapprochement bancaire Phase D (#5435) — US4 : état de rapprochement.
 */
class BankStatementStatusTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        return $company;
    }

    private function importCsv(Company $company, string $csv, string $reference = 'RELEVE-1'): BankStatement
    {
        return app(BankStatementImportService::class)->import(
            companyId: $company->id,
            statementPeriod: '2026-08',
            importReference: $reference,
            csvContent: $csv,
        )['statement'];
    }

    public function test_status_reports_balances_matched_pending_and_gap(): void
    {
        $company = $this->company();
        app()->instance('current_company', $company);

        $csv = "date;label;amount;reference\n"
            ."2026-08-05;Paiement facture FAC-2026-0001;1190.00;VIR-2026-001\n"
            ."2026-08-06;Virement fournisseur;3000.00;VIR-FOURNISSEUR\n";
        $statement = $this->importCsv($company, $csv);
        $statement->forceFill(['opening_balance' => 5000.00, 'closing_balance' => 7190.00])->save();

        $status = app(BankReconciliationService::class)->status($statement);

        $this->assertSame(2, $status['total_lines']);
        $this->assertSame(0, $status['matched_lines']);
        $this->assertSame(2, $status['pending_lines']);
        $this->assertSame(5000.0, $status['opening_balance']);
        // solde attendu = 5000 + 1190 + 3000 = 9190 ; écart vs 7190 déclaré = 2000
        $this->assertSame(9190.0, $status['closing_balance_expected']);
        $this->assertSame(7190.0, $status['closing_balance_reported']);
        $this->assertSame(2000.0, $status['closing_gap']);
    }
}
