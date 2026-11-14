<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\User;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Billing\Domain\Models\Partner;
use App\Modules\Billing\Domain\Models\PartnerReferral;
use App\Modules\Payroll\Domain\Models\Commission;
use App\Modules\Payroll\Domain\Models\Payment;
use App\Modules\Payroll\Infrastructure\Services\CommissionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Couverture des branches de garde de CommissionService::recordCommissionForPayment().
 *
 * GrowthModuleTest couvre le chemin nominal (commission creee), le calcul sur
 * base HT et la limite de 12 mois. Les sorties anticipees (paiement non
 * complete, idempotence, societe/partenaire absent, partenaire suspendu,
 * commission nulle, course sur l'index unique) n'etaient exercees par aucun
 * test : c'est ce que ce fichier couvre.
 *
 * Les montants sont calcules a la main, jamais repris de l'implementation.
 */
class CommissionServiceTest extends TestCase
{
    use CreatesMvpSchema;

    private CommissionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        $this->service = app(CommissionService::class);

        // La fixture MVP cree `commissions` sans l'index unique sur payment_id
        // que pose la migration 2026_08_15_000011. On le pose ici pour pouvoir
        // exercer reellement la branche 23505 (course check-then-create).
        if (! $this->hasPaymentIdUniqueIndex()) {
            Schema::table('commissions', function ($table): void {
                $table->unique('payment_id', 'commissions_payment_id_unique');
            });
        }

        // Connexion jumelle, hors transaction de test : sert a simuler une
        // ecriture concurrente qui doit survivre au rollback du savepoint.
        config([
            'database.connections.pgsql_race' => array_merge(
                config('database.connections.pgsql'),
                ['search_path' => 'public,shared_tenants'],
            ),
        ]);
        DB::purge('pgsql_race');
    }

    protected function tearDown(): void
    {
        // La ligne inseree par la connexion jumelle est committee hors de la
        // transaction de test : on la retire explicitement pour ne pas polluer
        // les tests suivants (payment_id unique).
        if (isset($this->racePaymentId)) {
            DB::connection('pgsql_race')
                ->table('commissions')
                ->where('payment_id', $this->racePaymentId)
                ->delete();
        }

        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    private ?int $racePaymentId = null;

    private function hasPaymentIdUniqueIndex(): bool
    {
        return (bool) DB::table('information_schema.table_constraints')
            ->where('constraint_name', 'commissions_payment_id_unique')
            ->where('table_name', 'commissions')
            ->exists();
    }

    private function partner(array $attributes = []): Partner
    {
        $user = User::factory()->create();

        /** @var Partner $partner */
        $partner = Partner::create(array_merge([
            'user_id' => $user->id,
            'referral_code' => 'CST_'.uniqid(),
            'default_commission_rate' => 1000, // 10 %
            'tax_rate' => 0,
            'status' => 'active',
        ], $attributes));

        return $partner;
    }

    private function company(?Partner $partner = null): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        if ($partner !== null) {
            $company->referrer_partner_id = $partner->id;
            $company->save();
        }

        return $company;
    }

    private function payment(Company $company, array $attributes = []): Payment
    {
        /** @var Payment $payment */
        $payment = Payment::create(array_merge([
            'invoice_id' => random_int(100000, 999990),
            'company_id' => $company->id,
            'amount' => 100.00,
            'currency' => 'DZD',
            'status' => 'completed',
            'paid_at' => now(),
        ], $attributes));

        return $payment;
    }

    public function test_returns_null_when_payment_is_not_completed(): void
    {
        $partner = $this->partner();
        $company = $this->company($partner);
        $payment = $this->payment($company, ['status' => 'pending']);

        $this->assertNull($this->service->recordCommissionForPayment($payment));
        $this->assertSame(0, Commission::query()->count());
    }

    public function test_returns_null_when_commission_already_exists_for_payment(): void
    {
        $partner = $this->partner();
        $company = $this->company($partner);
        $payment = $this->payment($company);

        $first = $this->service->recordCommissionForPayment($payment);
        $this->assertNotNull($first);

        // Second appel : l'idempotence doit renvoyer null sans creer de doublon.
        $this->assertNull($this->service->recordCommissionForPayment($payment));
        $this->assertSame(1, Commission::query()->count());
    }

    public function test_returns_null_when_company_does_not_exist(): void
    {
        $partner = $this->partner();

        /** @var Payment $payment */
        $payment = Payment::create([
            'invoice_id' => random_int(100000, 999990),
            'company_id' => '00000000-0000-4000-8000-000000000000',
            'amount' => 100.00,
            'currency' => 'DZD',
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        $this->assertNull($this->service->recordCommissionForPayment($payment));
        $this->assertSame(0, Commission::query()->count());
    }

    public function test_returns_null_when_company_has_no_referrer_partner(): void
    {
        $company = $this->company();
        $payment = $this->payment($company);

        $this->assertNull($this->service->recordCommissionForPayment($payment));
        $this->assertSame(0, Commission::query()->count());
    }

    public function test_returns_null_when_referrer_partner_row_is_missing(): void
    {
        $company = $this->company();
        $company->referrer_partner_id = 999999;
        $company->save();

        $payment = $this->payment($company);

        $this->assertNull($this->service->recordCommissionForPayment($payment));
        $this->assertSame(0, Commission::query()->count());
    }

    public function test_returns_null_when_partner_is_not_active(): void
    {
        $partner = $this->partner(['status' => 'suspended']);
        $company = $this->company($partner);
        $payment = $this->payment($company);

        $this->assertNull($this->service->recordCommissionForPayment($payment));
        $this->assertSame(0, Commission::query()->count());
    }

    public function test_returns_null_when_computed_commission_is_zero(): void
    {
        // Taux 0 % : la commission calculee vaut 0, aucune ligne ne doit etre creee.
        $partner = $this->partner(['default_commission_rate' => 0]);
        $company = $this->company($partner);
        $payment = $this->payment($company);

        $this->assertNull($this->service->recordCommissionForPayment($payment));
        $this->assertSame(0, Commission::query()->count());
    }

    public function test_records_commission_within_the_twelve_month_window(): void
    {
        // Contre-epreuve de la limite : un parrainage de 11 mois reste eligible.
        $partner = $this->partner();
        $company = $this->company($partner);

        PartnerReferral::create([
            'partner_id' => $partner->id,
            'company_id' => $company->id,
            'referred_at' => now()->subMonths(11),
        ]);

        $payment = $this->payment($company);

        $commission = $this->service->recordCommissionForPayment($payment);

        $this->assertNotNull($commission);
        // 100,00 DZD = 10 000 centimes, 10 % = 1 000 centimes.
        $this->assertSame(1000, $commission->amount);
        $this->assertSame(10000, $commission->net_amount);
        $this->assertSame('pending', $commission->status);
    }

    public function test_concurrent_create_on_unique_index_is_swallowed(): void
    {
        $partner = $this->partner();
        $company = $this->company($partner);
        $payment = $this->payment($company);
        $this->racePaymentId = $payment->id;

        // Simule la course : une autre requete insere la commission entre le
        // exists() d'idempotence et le create(). L'insertion passe par une
        // connexion SEPAREE (autocommit) : elle survit au rollback du savepoint
        // que le service declenche sur sa propre connexion, exactement comme le
        // ferait une requete concurrente. Le create() du service viole alors
        // l'index unique -> 23505 -> null, sans 500.
        Commission::creating(function () use ($payment, $partner, $company): void {
            DB::connection('pgsql_race')->table('commissions')->insert([
                'partner_id' => $partner->id,
                'company_id' => $company->id,
                'payment_id' => $payment->id,
                'amount' => 1000,
                'net_amount' => 10000,
                'currency' => 'DZD',
                'applied_rate' => 1000,
                'exchange_rate' => 1.0,
                'original_amount' => 10000,
                'original_currency' => 'DZD',
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $result = $this->service->recordCommissionForPayment($payment);

        $this->assertNull($result, 'La course doit etre absorbee (idempotence), pas propagee.');
        $this->assertSame(1, Commission::query()->where('payment_id', $payment->id)->count());
    }
}
