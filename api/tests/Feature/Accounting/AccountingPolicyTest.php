<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Enums\DocumentStatus;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingFixedAsset;
use App\Modules\Accounting\Domain\Models\AccountingTaxRate;
use App\Modules\Accounting\Policies\AccountingPolicy;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #9379 — policies d'autorisation.
 *
 * Le défaut audité : aucune Policy dans le module. Toute l'autorisation
 * reposait sur `api.manager:comptable,principal`, qui ne répond qu'à « ce rôle
 * accède-t-il au module ? » et jamais à « peut-il annuler une facture payée ? »
 * ou « peut-il clôturer une période ? ».
 */
class AccountingPolicyTest extends TestCase
{
    use RefreshTenantDatabase;

    private function actor(string $managerRole, ?Company $company = null): Employee
    {
        $company ??= Company::factory()->create();

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => $managerRole,
        ]);

        return $employee;
    }

    private function document(Company $company, float $paid = 0.0): AccountingDocument
    {
        /** @var AccountingDocument $document */
        $document = AccountingDocument::query()->create([
            'company_id' => $company->id,
            'type' => 'invoice',
            'number' => 'FAC-'.uniqid(),
            'status' => DocumentStatus::Sent->value,
            'issue_date' => '2026-08-01',
            'subtotal_ht' => 1000,
            'tax_amount' => 190,
            'total_ttc' => 1190,
            'paid_amount' => $paid,
        ]);

        return $document;
    }

    public function test_principal_and_accountant_can_read_the_module(): void
    {
        $policy = new AccountingPolicy;

        $this->assertTrue($policy->viewAny($this->actor('principal')));
        $this->assertTrue($policy->viewAny($this->actor('comptable')));
    }

    public function test_other_manager_roles_cannot_read_the_module(): void
    {
        $policy = new AccountingPolicy;

        $this->assertFalse($policy->viewAny($this->actor('rh')));
        $this->assertFalse($policy->viewAny($this->actor('marketing')));
        $this->assertFalse($policy->viewAny($this->actor('dept')));
    }

    /**
     * Règle centrale : un document payé ne s'annule pas, quel que soit le rôle.
     */
    public function test_paid_document_cannot_be_cancelled(): void
    {
        $company = Company::factory()->create();
        $policy = new AccountingPolicy;
        $document = $this->document($company, 1190.0);

        $this->assertFalse($policy->cancel($this->actor('comptable', $company), $document));
        $this->assertFalse($policy->cancel($this->actor('principal', $company), $document));
    }

    public function test_unpaid_document_can_be_cancelled_by_an_accountant(): void
    {
        $company = Company::factory()->create();
        $policy = new AccountingPolicy;
        $document = $this->document($company, 0.0);

        $this->assertTrue($policy->cancel($this->actor('comptable', $company), $document));
    }

    /**
     * Isolation tenant : un acteur ne touche jamais la pièce d'une autre
     * entreprise, même avec le bon rôle.
     */
    public function test_cross_tenant_document_is_never_cancellable(): void
    {
        $mine = Company::factory()->create();
        $other = Company::factory()->create();

        $policy = new AccountingPolicy;
        $document = $this->document($other, 0.0);

        $this->assertFalse($policy->cancel($this->actor('principal', $mine), $document));
        $this->assertFalse($policy->view($this->actor('principal', $mine), $document));
    }

    /**
     * Clôture : principal uniquement (séparation des tâches).
     */
    public function test_only_principal_can_close_a_period(): void
    {
        $policy = new AccountingPolicy;

        $this->assertTrue($policy->closePeriod($this->actor('principal')));
        $this->assertFalse($policy->closePeriod($this->actor('comptable')));
    }

    /**
     * Référentiel de TVA : paramétrage fiscal structurant, réservé au principal.
     */
    public function test_only_principal_can_manage_tax_rates(): void
    {
        $company = Company::factory()->create();
        $policy = new AccountingPolicy;

        $this->assertTrue($policy->manageTaxRates($this->actor('principal', $company)));
        $this->assertFalse($policy->manageTaxRates($this->actor('comptable', $company)));

        /** @var AccountingTaxRate $rate */
        $rate = AccountingTaxRate::query()->create([
            'company_id' => $company->id,
            'code' => 'TVA-STD-19',
            'label' => 'TVA standard',
            'rate' => 19,
            'kind' => 'standard',
            'is_default' => true,
            'is_active' => true,
        ]);

        // Le principal d'une AUTRE entreprise ne peut pas y toucher.
        $this->assertFalse($policy->manageTaxRates($this->actor('principal'), $rate));
    }

    /**
     * Immobilisations : gestion au comptable, suppression au principal.
     */
    public function test_fixed_asset_deletion_is_reserved_to_principal(): void
    {
        $company = Company::factory()->create();
        $policy = new AccountingPolicy;

        /** @var AccountingFixedAsset $asset */
        $asset = AccountingFixedAsset::query()->create([
            'company_id' => $company->id,
            'code' => 'IMM-1',
            'label' => 'Serveur',
            'in_service_date' => '2026-01-01',
            'acquisition_cost' => 5000,
            'residual_value' => 0,
            'useful_life_months' => 36,
            'method' => 'linear',
            'currency' => 'DZD',
            'status' => 'active',
        ]);

        $this->assertTrue($policy->manageFixedAssets($this->actor('comptable', $company), $asset));
        $this->assertFalse($policy->deleteFixedAsset($this->actor('comptable', $company), $asset));
        $this->assertTrue($policy->deleteFixedAsset($this->actor('principal', $company), $asset));
    }

    /**
     * La Policy est bien branchée sur les modèles (Gate::policy).
     */
    public function test_policy_is_registered_on_the_models(): void
    {
        $company = Company::factory()->create();
        $document = $this->document($company, 0.0);

        $accountant = $this->actor('comptable', $company);
        $this->assertTrue($accountant->can('cancel', $document));

        $hr = $this->actor('rh', $company);
        $this->assertFalse($hr->can('cancel', $document));
    }
}
