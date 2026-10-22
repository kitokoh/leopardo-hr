<?php

declare(strict_types=1);

namespace Tests\Feature\Paiements;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Paiements\Domain\Models\PaymentProviderInstance;
use App\Modules\Paiements\Domain\Models\PaymentTransaction;
use App\Modules\Paiements\Domain\Models\PaymentWebhookEndpoint;
use App\Modules\Paiements\Domain\Models\Wallet;
use App\Modules\Paiements\Policies\PaymentProviderInstancePolicy;
use App\Modules\Paiements\Policies\PaymentTransactionPolicy;
use App\Modules\Paiements\Policies\PaymentWebhookEndpointPolicy;
use App\Modules\Paiements\Policies\WalletPolicy;
use Illuminate\Support\Facades\Gate;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-38 PAIEMENTS — #8711 : policies explicites sur les modeles sensibles.
 *
 * Chaque regle est verifiee **dans les deux sens** : l'acteur legitime est
 * autorise ET chaque refus attendu est asserte separement (autre role, autre
 * tenant, instance globale). Un test qui n'asserte que le positif passerait
 * aussi avec une policy qui renvoie `true`.
 *
 * Les policies sont resolues via le Gate (donc via l'enregistrement reel dans
 * `AuthServiceProvider`) et non instanciees a la main : sinon un oubli
 * d'enregistrement laisserait la suite verte alors que l'application n'a
 * aucune policy.
 */
final class PaymentPoliciesTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Company $otherCompany;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        // Second tenant distinct : l'isolation inter-tenant doit se prouver sur
        // deux companies REELLES, pas sur un identifiant fabrique.
        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->otherCompany = $other;
    }

    public function test_policies_are_registered_on_the_gate(): void
    {
        $this->assertInstanceOf(
            WalletPolicy::class,
            Gate::getPolicyFor(Wallet::class),
            'Wallet doit avoir une policy enregistree : sans elle, l\'autorisation '
            .'repose uniquement sur le scope de requete et une regression passe inapercue.'
        );
        $this->assertInstanceOf(PaymentTransactionPolicy::class, Gate::getPolicyFor(PaymentTransaction::class));
        $this->assertInstanceOf(PaymentProviderInstancePolicy::class, Gate::getPolicyFor(PaymentProviderInstance::class));
        $this->assertInstanceOf(PaymentWebhookEndpointPolicy::class, Gate::getPolicyFor(PaymentWebhookEndpoint::class));
    }

    public function test_principal_can_view_and_request_cash_out_on_its_own_wallet(): void
    {
        $principal = $this->employee('manager');
        $wallet = $this->wallet($this->company);
        $policy = new WalletPolicy;

        $this->assertTrue($policy->view($principal, $wallet), 'principal doit lire son wallet');
        $this->assertTrue($policy->requestCashOut($principal, $wallet), 'principal doit pouvoir demander un retrait');
    }

    public function test_hr_can_view_but_not_request_cash_out(): void
    {
        $hr = $this->employee('managerRh');
        $wallet = $this->wallet($this->company);
        $policy = new WalletPolicy;

        $this->assertTrue($policy->view($hr, $wallet), 'rh doit lire le wallet (pilotage/paye)');
        $this->assertFalse(
            $policy->requestCashOut($hr, $wallet),
            'rh ne doit PAS engager le compte bancaire : la demande de retrait est reservee au principal'
        );
    }

    public function test_plain_employee_cannot_view_or_request_cash_out(): void
    {
        $employee = $this->employee(null);
        $wallet = $this->wallet($this->company);
        $policy = new WalletPolicy;

        $this->assertFalse($policy->view($employee, $wallet), 'un employe sans manager_role ne lit pas le wallet');
        $this->assertFalse($policy->requestCashOut($employee, $wallet));
    }

    public function test_principal_of_another_tenant_is_refused(): void
    {
        $principal = $this->employee('manager');
        $foreignWallet = $this->wallet($this->otherCompany);
        $policy = new WalletPolicy;

        $this->assertFalse(
            $policy->view($principal, $foreignWallet),
            'Isolation inter-tenant : le wallet d\'une autre company doit etre refuse meme pour un principal'
        );
        $this->assertFalse($policy->requestCashOut($principal, $foreignWallet));
    }

    public function test_transaction_policy_separates_read_from_operate(): void
    {
        $principal = $this->employee('manager');
        $hr = $this->employee('managerRh');
        $transaction = new PaymentTransaction;
        $transaction->company_id = $this->company->id;
        $policy = new PaymentTransactionPolicy;

        $this->assertTrue($policy->view($principal, $transaction));
        $this->assertTrue($policy->operate($principal, $transaction), 'principal opere');

        $this->assertTrue($policy->view($hr, $transaction));
        $this->assertFalse($policy->operate($hr, $transaction), 'rh lit la transaction mais n\'opere pas');

        $foreign = new PaymentTransaction;
        $foreign->company_id = $this->otherCompany->id;
        $this->assertFalse($policy->view($principal, $foreign), 'isolation inter-tenant');
        $this->assertFalse($policy->operate($principal, $foreign));
    }

    public function test_provider_instance_of_another_tenant_is_refused(): void
    {
        $principal = $this->employee('manager');
        $policy = new PaymentProviderInstancePolicy;

        $own = new PaymentProviderInstance;
        $own->company_id = $this->company->id;

        $foreign = new PaymentProviderInstance;
        $foreign->company_id = $this->otherCompany->id;

        $this->assertTrue($policy->view($principal, $own));
        $this->assertFalse($policy->view($principal, $foreign), 'instance d\'un autre tenant');
        $this->assertFalse($policy->update($principal, $own), 'la configuration d\'un provider est plateforme, pas tenant');
    }

    public function test_global_provider_instance_is_never_visible_to_a_tenant(): void
    {
        $principal = $this->employee('manager');
        $policy = new PaymentProviderInstancePolicy;

        $global = new PaymentProviderInstance;
        $global->company_id = null;

        $this->assertFalse(
            $policy->view($principal, $global),
            'company_id = null signifie instance GLOBALE : deux null ne doivent pas etre lus comme une correspondance'
        );
    }

    public function test_webhook_endpoint_is_never_reachable_from_a_tenant(): void
    {
        $principal = $this->employee('manager');
        $policy = new PaymentWebhookEndpointPolicy;

        $endpoint = new PaymentWebhookEndpoint;
        $endpoint->company_id = $this->company->id;

        $this->assertFalse($policy->view($principal, $endpoint), 'l\'endpoint porte un secret de signature');
        $this->assertFalse($policy->create($principal));
        $this->assertFalse($policy->update($principal, $endpoint));
    }

    /**
     * Cree un employe reel du tenant principal.
     *
     * @param  string|null  $state  'manager' (principal), 'managerRh' (rh), null (employe simple)
     */
    private function employee(?string $state): Employee
    {
        $factory = Employee::factory();

        if ($state === 'manager') {
            $factory = $factory->manager();
        } elseif ($state === 'managerRh') {
            $factory = $factory->managerRh();
        }

        /** @var Employee $employee */
        $employee = $factory->create(['company_id' => $this->company->id]);

        return $employee;
    }

    private function wallet(Company $company): Wallet
    {
        $wallet = new Wallet;
        $wallet->company_id = $company->id;

        return $wallet;
    }
}
