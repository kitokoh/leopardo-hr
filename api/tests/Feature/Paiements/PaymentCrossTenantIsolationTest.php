<?php

declare(strict_types=1);

namespace Tests\Feature\Paiements;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Paiements\Domain\Enums\ProviderInstanceStatus;
use App\Modules\Paiements\Domain\Enums\WalletOwnerType;
use App\Modules\Paiements\Domain\Models\PaymentProviderInstance;
use App\Modules\Paiements\Domain\Models\PaymentTransaction;
use App\Modules\Paiements\Domain\Models\Wallet;
use App\Modules\Paiements\Infrastructure\Services\LedgerService;
use App\Modules\Paiements\Infrastructure\Services\PaymentService;
use App\Modules\Paiements\Infrastructure\Services\Rails\SimulatorRail;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-38 PAIEMENTS — #8715 : acces croise inter-tenant sur les surfaces Paiements.
 *
 * L'audit de readiness (`docs/audits/readiness-identite-paiements.md`, ecart
 * P-5) constate que les 22 tests du BC-38 couvrent le domaine, l'idempotence et
 * le resolver, mais qu'**aucun** ne verifie l'autorisation croisee
 * Superadmin <-> Tenant. Ce fichier comble ce trou, sur les trois criteres
 * d'acceptation de l'issue :
 *
 *   1. tenant A ne peut pas lire le wallet / les transactions du tenant B ;
 *   2. un utilisateur tenant ne peut pas appeler les endpoints Superadmin ;
 *   3. le cash-out d'un tenant n'affecte jamais le wallet d'un autre tenant.
 *
 * Chaque assertion est faite sur DEUX tenants reels (pas un identifiant
 * fabrique) : l'isolation se prouve en montrant que l'etat de B est inchange
 * apres une action de A, pas seulement que A ne voit pas B.
 *
 * Note sur l'observation cross-tenant : le scope global `company`
 * (`BelongsToCompany`) est fail-closed et suit le tenant authentifie. Pour
 * observer l'etat d'un AUTRE tenant depuis le test, on passe donc par le
 * wrapper documente `crossTenantForPlatformAdmin()` (#7960) — jamais par un
 * `withoutGlobalScope` brut.
 */
final class PaymentCrossTenantIsolationTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyB = $companyB;

        // Instance simulateur globale active : le cash-out doit pouvoir resoudre
        // un rail sans dependre d'un fournisseur externe.
        PaymentProviderInstance::query()->create([
            'company_id' => null,
            'type_code' => SimulatorRail::CODE,
            'display_name' => 'Simulateur (test)',
            'countries' => [],
            'currencies' => [],
            'status' => ProviderInstanceStatus::Active->value,
            'config' => [
                'success_rate' => 100,
                'webhook_success_rate' => 100,
                'delay_ms' => 0,
            ],
            'priority' => 100,
        ]);
    }

    public function test_tenant_a_cannot_read_tenant_b_wallet_balance(): void
    {
        $walletB = $this->fundTenant($this->companyB, 500_000);

        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->getJson('/api/v1/paiements/wallet')->assertOk();

        // A voit SON wallet (solde 0), jamais celui de B.
        $this->assertSame(0, $response->json('data.balance'));
        $this->assertNotSame((int) $walletB->id, (int) $response->json('data.wallet_id'));

        // Et l'etat de B est intact : l'isolation n'est pas seulement visuelle.
        $this->assertSame(500_000, $this->balanceOf($walletB));
    }

    public function test_tenant_a_history_never_contains_tenant_b_entries(): void
    {
        $walletB = $this->fundTenant($this->companyB, 250_000);

        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->getJson('/api/v1/paiements/wallet/history')->assertOk();

        // A n'a aucune ecriture : l'historique est vide, pas « celui de B ».
        $this->assertSame([], $response->json('data'));

        $walletIds = array_column((array) $response->json('data'), 'wallet_id');
        $this->assertNotContains((int) $walletB->id, $walletIds);
    }

    public function test_tenant_user_cannot_reach_platform_payments_surface(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        // La surface Superadmin du BC-38 est protegee par `auth:super_admin_api` :
        // un token tenant ne l'atteint pas.
        $this->getJson('/api/v1/platform/payments/provider-instances')->assertStatus(401);
        $this->getJson('/api/v1/platform/payments/transactions')->assertStatus(401);
    }

    public function test_super_admin_can_reach_platform_payments_surface(): void
    {
        $superAdmin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => 'admin@leopardo-rh.com',
        ]);
        $superAdmin->forceFill(['password_hash' => Hash::make('admin')])->save();

        $this->actingAs($superAdmin, 'super_admin_api')
            ->getJson('/api/v1/platform/payments/provider-instances')
            ->assertOk();
    }

    public function test_cash_out_of_tenant_a_never_touches_tenant_b_wallet(): void
    {
        $walletA = $this->fundTenant($this->companyA, 100_000);
        $walletB = $this->fundTenant($this->companyB, 100_000);

        Sanctum::actingAs($this->manager($this->companyA));

        $this->postJson('/api/v1/paiements/cash-out', [
            'amount' => 30_000,
            'currency' => 'DZD',
            'idempotency_key' => 'cashout-a-1',
        ])->assertStatus(201);

        // A a bien ete debite...
        $this->assertSame(70_000, $this->balanceOf($walletA));

        // ...et B n'a pas bouge d'un centime.
        $this->assertSame(100_000, $this->balanceOf($walletB));

        // La transaction appartient au tenant A, et une seule a ete creee.
        $this->assertSame(1, $this->transactionCount($this->companyA));
        $this->assertSame(0, $this->transactionCount($this->companyB));
    }

    public function test_cash_out_is_scoped_to_the_authenticated_tenant(): void
    {
        $walletA = $this->fundTenant($this->companyA, 100_000);
        $walletB = $this->fundTenant($this->companyB, 100_000);

        // B demande un retrait : c'est le wallet de B qui est debite, jamais A.
        Sanctum::actingAs($this->manager($this->companyB));

        $this->postJson('/api/v1/paiements/cash-out', [
            'amount' => 40_000,
            'currency' => 'DZD',
            'idempotency_key' => 'cashout-b-1',
        ])->assertStatus(201);

        $this->assertSame(60_000, $this->balanceOf($walletB));
        $this->assertSame(100_000, $this->balanceOf($walletA));
    }

    /**
     * Credite le wallet tenant d'une company via le ledger (partie double).
     */
    private function fundTenant(Company $company, int $amount): Wallet
    {
        $ledger = app(LedgerService::class);
        $payments = app(PaymentService::class);

        $platform = $payments->walletFor((string) $company->id, WalletOwnerType::Platform, 'platform', 'DZD');
        $tenant = $payments->walletFor((string) $company->id, WalletOwnerType::Tenant, (string) $company->id, 'DZD');

        $ledger->post((string) $company->id, (int) $platform->id, (int) $tenant->id, $amount, 'DZD');

        return $tenant;
    }

    /**
     * Solde d'un wallet observe hors du tenant authentifie (#7960).
     */
    private function balanceOf(Wallet $wallet): int
    {
        /** @var Wallet $fresh */
        $fresh = Wallet::query()->crossTenantForPlatformAdmin()->findOrFail($wallet->id);

        return $fresh->balance();
    }

    private function transactionCount(Company $company): int
    {
        return PaymentTransaction::query()
            ->crossTenantForPlatformAdmin()
            ->where('company_id', $company->id)
            ->count();
    }

    private function manager(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->manager()->create(['company_id' => $company->id]);

        return $employee;
    }
}
