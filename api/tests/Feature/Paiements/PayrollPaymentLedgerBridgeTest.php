<?php

declare(strict_types=1);

namespace Tests\Feature\Paiements;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Paiements\Domain\Enums\WalletOwnerType;
use App\Modules\Paiements\Domain\Models\PaymentLedgerEntry;
use App\Modules\Paiements\Domain\Models\PaymentProviderInstance;
use App\Modules\Paiements\Infrastructure\Services\LedgerService as PaymentLedgerService;
use App\Modules\Paiements\Infrastructure\Services\PaymentService;
use App\Modules\Paiements\Infrastructure\Services\Rails\SimulatorRail;
use App\Modules\Payroll\Domain\Models\LedgerEntry as PayrollLedgerEntry;
use App\Modules\Payroll\Infrastructure\Services\LedgerService as PayrollLedgerService;
use App\Support\Money;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-38 PAIEMENTS / BC-07 PAYROLL (#8713) — pont comptable entre les deux
 * journaux.
 *
 * L'ecart P-3 de l'audit readiness pointait deux sources de verite comptables
 * sans pont documente : `ledger_entries` (paie, `decimal(12,2)`, solde stocke)
 * et `payment_ledger_entries` (BC-38, `bigint`, partie double, solde derive).
 * Le risque nomme etait un **double comptage** lors d'un cash-out de salaire.
 *
 * L'ADR BC-38-005 tranche : les deux journaux restent separes (granularites et
 * invariants incompatibles), le pont est **evenementiel** (aucun module
 * n'importe l'autre — garde #5584), et la conversion passe par l'unique
 * convertisseur `App\Support\Money` (#8714).
 *
 * Ce test verrouille la consequence verifiable de cette decision : un cash-out
 * de salaire produit des ecritures **coherentes dans les deux journaux** — meme
 * valeur convertie, et journal de paiement toujours equilibre.
 */
class PayrollPaymentLedgerBridgeTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $employee;

    private PayrollLedgerService $payrollLedger;

    private PaymentService $payments;

    private PaymentLedgerService $paymentLedger;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $this->employee = $employee;

        $this->payrollLedger = app(PayrollLedgerService::class);
        $this->payments = app(PaymentService::class);
        $this->paymentLedger = app(PaymentLedgerService::class);

        PaymentProviderInstance::query()->create([
            'company_id' => null,
            'type_code' => SimulatorRail::CODE,
            'display_name' => 'Simulateur (pont paie)',
            'countries' => [],
            'currencies' => [],
            'status' => 'active',
            'config' => ['success_rate' => 100],
            'priority' => 10,
        ]);
    }

    public function test_salary_cash_out_produces_coherent_entries_in_both_journals(): void
    {
        // 1. Cote PAIE : le salaire est du a l'employe, en decimal.
        $salaryDecimal = '125000.00';

        $payrollEntry = $this->payrollLedger->record(
            employee: $this->employee,
            entryType: PayrollLedgerEntry::TYPE_PAYMENT,
            amount: (float) $salaryDecimal,
            description: 'Paiement de salaire (test pont)',
            currency: 'DZD',
        );

        // 2. Cote PAIEMENTS : le cash-out sort les fonds, en plus petite unite.
        $salaryMinorUnits = Money::fromDecimal($salaryDecimal, 'DZD')->minorUnits();

        $transaction = $this->payments->cashOut(
            companyId: (string) $this->company->id,
            amount: $salaryMinorUnits,
            currency: 'DZD',
            idempotencyKey: 'salary-bridge-1',
        );

        // 3. Coherence : les deux journaux portent la MEME valeur, convertie par
        //    l'unique convertisseur. Un ecart ici signifierait qu'un appelant a
        //    contourne `Money`.
        $this->assertSame(
            $salaryMinorUnits,
            Money::fromDecimal((string) $payrollEntry->amount, 'DZD')->minorUnits(),
            'Le montant de paie et le montant de paiement doivent etre la meme valeur convertie.',
        );

        $this->assertSame($salaryMinorUnits, (int) $transaction->amount);

        // 4. Le journal de paiement reste en partie double stricte : deux
        //    ecritures, somme debit == somme credit.
        $entries = PaymentLedgerEntry::query()->where('transaction_id', $transaction->id)->get();

        $this->assertCount(2, $entries);

        $inspection = $this->paymentLedger->inspectGroup(
            (string) $this->company->id,
            (string) $entries->firstOrFail()->transfer_group_id,
        );

        $this->assertTrue($inspection['balanced']);
        $this->assertSame($inspection['debits'], $inspection['credits']);
    }

    public function test_payroll_journal_keeps_its_own_running_balance(): void
    {
        // Le solde stocke cote paie est une seconde source de verite LOCALE au
        // journal employe : il n'est pas reconcilie avec le ledger de paiement,
        // et ne doit pas l'etre (deux soldes differents : du a l'employe vs.
        // position de tresorerie). Ce test fige cette separation assumee.
        $this->payrollLedger->record(
            employee: $this->employee,
            entryType: PayrollLedgerEntry::TYPE_ADVANCE,
            amount: -50_000.0,
            description: 'Avance sur salaire',
            currency: 'DZD',
        );

        $this->payrollLedger->record(
            employee: $this->employee,
            entryType: PayrollLedgerEntry::TYPE_PAYMENT,
            amount: 20_000.0,
            description: 'Paiement partiel',
            currency: 'DZD',
        );

        $this->assertSame(-30_000.0, $this->payrollLedger->currentBalance($this->employee));

        // Aucune ecriture de paiement n'a ete creee par le journal de paie :
        // le pont est evenementiel, pas un appel direct entre modules.
        $this->assertSame(0, PaymentLedgerEntry::query()->count());
    }

    public function test_conversion_is_the_single_source_of_truth_for_the_bridge(): void
    {
        // Le pont ne peut pas etre un service qui appelle les deux journaux :
        // la garde d'isolation (#5584) interdit a un module d'importer l'autre.
        // La coherence repose donc entierement sur le convertisseur partage.
        $this->assertSame(125_000, Money::fromDecimal('1250.00', 'DZD')->minorUnits());
        $this->assertSame('1250.00', Money::fromMinorUnits(125_000, 'DZD')->toDecimal());

        // Et l'arrondi reste symetrique, donc une paire debit/credit de meme
        // montant convertie par `Money` reste equilibree.
        $this->assertSame(
            0,
            Money::fromDecimal('-0.005', 'DZD')->minorUnits()
                + Money::fromDecimal('0.005', 'DZD')->minorUnits(),
        );
    }

    public function test_wallet_balance_reflects_the_cash_out_in_minor_units(): void
    {
        $wallet = $this->payments->walletFor(
            (string) $this->company->id,
            WalletOwnerType::Tenant,
            (string) $this->company->id,
            'DZD',
        );

        $this->assertSame(0, $wallet->balance());

        $this->payments->cashOut(
            companyId: (string) $this->company->id,
            amount: 75_000,
            currency: 'DZD',
            idempotencyKey: 'salary-bridge-2',
        );

        // Le solde derive du ledger de paiement est en plus petite unite, et
        // correspond exactement a la conversion du montant decimal.
        $this->assertSame(-75_000, $wallet->balance());
        $this->assertSame('-750.00', Money::fromMinorUnits($wallet->balance(), 'DZD')->toDecimal());
    }
}
