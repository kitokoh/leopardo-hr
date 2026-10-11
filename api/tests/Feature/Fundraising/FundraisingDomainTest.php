<?php

declare(strict_types=1);

namespace Tests\Feature\Fundraising;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Fundraising\Domain\Enums\FundraiserStatus;
use App\Modules\Fundraising\Domain\Models\Fundraiser;
use App\Modules\Fundraising\Domain\Models\FundraiserPublicLink;
use App\Modules\Fundraising\Domain\Models\FundraisingContribution;
use App\Modules\Fundraising\Domain\Models\FundraisingPaymentRoute;
use App\Modules\Fundraising\Domain\Policies\FundraiserPolicy;
use App\Modules\Fundraising\Domain\Support\ReferenceGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Verticale FUNDRAISING — socle domaine : migrations tenant idempotentes
 * (schema shared_tenants), annuaires publics (schema public), unicité
 * globale du slug et des références, scope tenant, feature flag
 * `fundraising` deny-by-default et RBAC (gestion réservée principal/rh).
 */
class FundraisingDomainTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $principalA;

    private Employee $employeeA;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CI', 'currency' => 'XOF']);
        $this->companyB = $companyB;

        /** @var Employee $principalA */
        $principalA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        $this->principalA = $principalA;

        /** @var Employee $employeeA */
        $employeeA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'employee',
            'status' => 'active',
        ]);
        $this->employeeA = $employeeA;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function fundraiser(Company $company, array $overrides = []): Fundraiser
    {
        /** @var Fundraiser $fundraiser */
        $fundraiser = Fundraiser::query()->create(array_merge([
            'company_id' => $company->id,
            'slug' => 'aide-fatou-'.strtolower(bin2hex(random_bytes(4))),
            'title' => 'Aide pour Fatou',
            'beneficiary_name' => 'Fatou Ndiaye',
            'goal_amount' => 500000,
            'currency' => 'XOF',
            'status' => FundraiserStatus::DRAFT,
        ], $overrides));

        return $fundraiser;
    }

    public function test_tables_exist_in_expected_schemas(): void
    {
        $this->assertTrue(Schema::hasTable('fundraisers'));
        $this->assertTrue(Schema::hasTable('fundraising_contributions'));
        $this->assertTrue(Schema::hasTable('fundraising_payouts'));
        $this->assertTrue(Schema::hasTable('fundraising_payment_events'));

        $schema = DB::selectOne(
            'SELECT table_schema FROM information_schema.tables WHERE table_name = ? LIMIT 1',
            ['fundraisers']
        );
        $this->assertSame('shared_tenants', $schema->table_schema ?? null, 'fundraisers absente du schéma tenant');

        // Les annuaires publics vivent dans le schéma public (landlord) :
        // Schema::hasTable ne sonde que le schéma courant (shared_tenants),
        // vérification directe dans information_schema.
        foreach (['fundraiser_public_links', 'fundraising_payment_routes'] as $table) {
            $schemaPublic = DB::selectOne(
                'SELECT table_schema FROM information_schema.tables WHERE table_name = ? LIMIT 1',
                [$table]
            );
            $this->assertSame('public', $schemaPublic->table_schema ?? null, $table.' absente du schéma public');
        }
    }

    public function test_fundraiser_defaults_and_company_id_not_null(): void
    {
        $fundraiser = $this->fundraiser($this->companyA);

        $this->assertSame($this->companyA->id, $fundraiser->company_id);
        $this->assertSame(FundraiserStatus::DRAFT, $fundraiser->status);

        // Défauts de compteurs posés par la migration (relus en base).
        $fundraiser->refresh();
        $this->assertSame(0.0, (float) $fundraiser->collected_amount);
        $this->assertSame(0, $fundraiser->contributions_count);

        $this->assertSame(0, DB::table('fundraisers')->whereNull('company_id')->count());
    }

    public function test_slug_is_unique_globally_across_tenants(): void
    {
        $slug = 'aide-fatou-unique';
        $this->fundraiser($this->companyA, ['slug' => $slug]);

        // Même slug chez un AUTRE tenant → refusé (slug = clé publique
        // globale — savepoint #4978).
        $this->expectException(QueryException::class);
        DB::transaction(function () use ($slug): void {
            $this->fundraiser($this->companyB, ['slug' => $slug]);
        });
    }

    public function test_contribution_reference_is_unique_and_formatted(): void
    {
        $reference = ReferenceGenerator::contribution();
        $this->assertMatchesRegularExpression('/^FC-[A-Z0-9]{10}$/', $reference);
        $this->assertMatchesRegularExpression('/^FP-[A-Z0-9]{10}$/', ReferenceGenerator::payout());

        $fundraiser = $this->fundraiser($this->companyA);

        FundraisingContribution::query()->create([
            'company_id' => $this->companyA->id,
            'fundraiser_id' => $fundraiser->id,
            'reference' => $reference,
            'amount' => 5000,
            'currency' => 'XOF',
            'payment_method' => 'mobile_money',
            'provider' => 'mobile_money',
            'status' => 'pending',
        ]);

        $this->expectException(QueryException::class);
        DB::transaction(function () use ($fundraiser, $reference): void {
            FundraisingContribution::query()->create([
                'company_id' => $this->companyA->id,
                'fundraiser_id' => $fundraiser->id,
                'reference' => $reference,
                'amount' => 7000,
                'currency' => 'XOF',
                'payment_method' => 'cash',
                'provider' => 'manual',
                'status' => 'pending',
            ]);
        });
    }

    public function test_tenant_scope_scopes_to_current_company(): void
    {
        $this->fundraiser($this->companyA);
        $this->fundraiser($this->companyB);

        app()->instance('current_company', $this->companyA);
        app()->instance('tenant_scope_required', true);

        $this->assertSame(1, Fundraiser::query()->count());
    }

    public function test_public_directories_have_no_tenant_scope(): void
    {
        FundraiserPublicLink::query()->create([
            'slug' => 'aide-fatou-xyz',
            'company_id' => $this->companyA->id,
            'status' => 'active',
        ]);

        FundraisingPaymentRoute::query()->create([
            'provider' => 'mobile_money',
            'provider_reference' => 'MM-TEST123',
            'company_id' => $this->companyA->id,
            'contribution_reference' => 'FC-TEST12345',
        ]);

        // Annuaires plateforme : lisibles hors contexte tenant (pas de
        // scope company) — c'est leur rôle (résolution publique).
        $this->assertSame(1, FundraiserPublicLink::query()->count());
        $this->assertSame(1, FundraisingPaymentRoute::query()->count());
    }

    public function test_feature_flag_denied_by_default(): void
    {
        $this->assertFalse($this->companyA->hasFeature('fundraising'));

        // setFeature() ne fait que modifier l'attribut en mémoire (toggle
        // explicite réservé super-admin / console — l'appelant persiste) :
        // sans save(), le refresh() recharge la carte `features` depuis la
        // base et le flag reste à false.
        $this->companyA->setFeature('fundraising', true);
        $this->companyA->save();
        $this->assertTrue($this->companyA->refresh()->hasFeature('fundraising'));
    }

    public function test_policy_denies_management_to_plain_employee(): void
    {
        $policy = new FundraiserPolicy;
        $fundraiser = $this->fundraiser($this->companyA);

        $this->assertTrue($policy->create($this->principalA));
        $this->assertTrue($policy->update($this->principalA, $fundraiser));
        $this->assertFalse($policy->create($this->employeeA));
        $this->assertFalse($policy->update($this->employeeA, $fundraiser));

        // Lecture : membre du tenant uniquement.
        $this->assertTrue($policy->view($this->employeeA, $fundraiser));
    }

    public function test_available_balance_locks_requested_processing_paid(): void
    {
        $fundraiser = $this->fundraiser($this->companyA, [
            'collected_amount' => 100000,
            'status' => FundraiserStatus::CLOSED,
        ]);

        $fundraiser->payouts()->create([
            'company_id' => $this->companyA->id,
            'reference' => ReferenceGenerator::payout(),
            'amount' => 40000,
            'currency' => 'XOF',
            'method' => 'mobile_money',
            'recipient_name' => 'Fatou Ndiaye',
            'recipient_account' => '+221770000000',
            'status' => 'requested',
        ]);

        $this->assertSame(60000.0, $fundraiser->availableBalance());
    }
}
