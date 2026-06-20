<?php

namespace Tests\Feature;

use App\Models\Commission;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\User;
use App\Services\PartnerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrowthModuleTest extends TestCase
{
    use \Tests\Support\CreatesMvpSchema;

    private PartnerService $partnerService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        $this->partnerService = app(PartnerService::class);
        $this->commissionService = app(\App\Services\CommissionService::class);
    }

    public function test_can_attribute_company_to_partner()
    {
        $user = User::factory()->create(['email' => 'partner@example.com']);
        $partner = Partner::create([
            'user_id' => $user->id,
            'referral_code' => 'PARTNER123',
            'default_commission_rate' => 1000,
        ]);

        $company = Company::factory()->create([
            'email' => 'client@example.com',
            'referrer_partner_id' => null,
        ]);

        $result = $this->partnerService->attributeCompanyToPartner($company, 'PARTNER123');

        $this->assertTrue($result);
        $this->assertEquals($partner->id, $company->fresh()->referrer_partner_id);
    }

    public function test_prevents_self_referral()
    {
        $user = User::factory()->create(['email' => 'partner@example.com']);
        $partner = Partner::create([
            'user_id' => $user->id,
            'referral_code' => 'PARTNER123',
        ]);

        $company = Company::factory()->create([
            'email' => 'partner@example.com', // Same as partner
            'referrer_partner_id' => null,
        ]);

        $result = $this->partnerService->attributeCompanyToPartner($company, 'PARTNER123');

        $this->assertFalse($result);
        $this->assertNull($company->fresh()->referrer_partner_id);
    }

    public function test_records_commission_on_payment()
    {
        $user = User::factory()->create();
        $partner = Partner::create([
            'user_id' => $user->id,
            'referral_code' => 'PARTNER123',
            'default_commission_rate' => 1500, // 15%
        ]);

        $company = Company::factory()->create(['referrer_partner_id' => $partner->id]);

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'number' => 'INV-001',
            'amount' => 100.00,
            'tax_amount' => 0,
            'total' => 100.00,
            'status' => 'paid',
            'due_date' => now(),
        ]);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'company_id' => $company->id,
            'amount' => 100.00,
            'currency' => 'DZD',
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        $commission = $this->commissionService->recordCommissionForPayment($payment);

        $this->assertNotNull($commission);
        $this->assertEquals(1500, $commission->amount); // 15% of 100.00 (10000 cents) = 1500 cents
        $this->assertEquals(1500, $commission->applied_rate);
        $this->assertEquals('pending', $commission->status);
    }

    public function test_approves_commissions_after_delay()
    {
        $user = User::factory()->create();
        $partner = Partner::create(['user_id' => $user->id, 'referral_code' => 'P1']);

        $oldCommission = Commission::create([
            'partner_id' => $partner->id,
            'company_id' => '00000000-0000-0000-0000-000000000001',
            'payment_id' => 1,
            'amount' => 1000,
            'applied_rate' => 1000,
            'status' => 'pending',
            'created_at' => now()->subDays(15),
        ]);

        $recentCommission = Commission::create([
            'partner_id' => $partner->id,
            'company_id' => '00000000-0000-0000-0000-000000000001',
            'payment_id' => 2,
            'amount' => 1000,
            'applied_rate' => 1000,
            'status' => 'pending',
            'created_at' => now()->subDays(5),
        ]);

        $count = $this->partnerService->approvePendingCommissions();

        $this->assertEquals(1, $count);
        $this->assertEquals('approved', $oldCommission->fresh()->status);
        $this->assertEquals('pending', $recentCommission->fresh()->status);
    }

    public function test_cancels_commission_on_refund()
    {
        $user = User::factory()->create();
        $partner = Partner::create(['user_id' => $user->id, 'referral_code' => 'P1']);

        $payment = Payment::create([
            'invoice_id' => 1,
            'company_id' => '00000000-0000-0000-0000-000000000001',
            'amount' => 100,
            'status' => 'completed',
        ]);

        $commission = Commission::create([
            'partner_id' => $partner->id,
            'company_id' => '00000000-0000-0000-0000-000000000001',
            'payment_id' => $payment->id,
            'amount' => 1000,
            'applied_rate' => 1000,
            'status' => 'pending',
        ]);

        $payment->status = 'refunded';
        $this->partnerService->handlePaymentRefunded($payment);

        $this->assertEquals('cancelled', $commission->fresh()->status);
    }

    public function test_reassign_partner_with_audit_log()
    {
        $admin = User::factory()->create();
        $partner1 = Partner::create(['user_id' => User::factory()->create()->id, 'referral_code' => 'P1']);
        $partner2 = Partner::create(['user_id' => User::factory()->create()->id, 'referral_code' => 'P2']);

        $company = Company::factory()->create(['referrer_partner_id' => $partner1->id]);

        $this->partnerService->reassignCompanyPartner($company, $partner2->id, $admin->id, 'Commercial transfer');

        $this->assertEquals($partner2->id, $company->fresh()->referrer_partner_id);
        $this->assertDatabaseHas('partner_audit_logs', [
            'admin_id' => $admin->id,
            'auditable_type' => Company::class,
            'auditable_id' => $company->id,
            'event' => 'partner_reassignment',
            'reason' => 'Commercial transfer',
        ]);
    }

    public function test_update_partner_rate_with_audit_log()
    {
        $admin = User::factory()->create();
        $partner = Partner::create(['user_id' => User::factory()->create()->id, 'referral_code' => 'P1', 'default_commission_rate' => 1000]);

        $this->partnerService->updatePartnerRate($partner, 2000, $admin->id, 'Tier upgrade');

        $this->assertEquals(2000, $partner->fresh()->default_commission_rate);
        $this->assertDatabaseHas('partner_audit_logs', [
            'admin_id' => $admin->id,
            'auditable_type' => Partner::class,
            'auditable_id' => (string) $partner->id,
            'event' => 'rate_adjustment',
            'reason' => 'Tier upgrade',
        ]);
    }

    public function test_update_commission_status_with_audit_log()
    {
        $admin = User::factory()->create();
        $partner = Partner::create(['user_id' => User::factory()->create()->id, 'referral_code' => 'P1']);
        $commission = Commission::create([
            'partner_id' => $partner->id,
            'company_id' => '00000000-0000-0000-0000-000000000001',
            'payment_id' => 1,
            'amount' => 1000,
            'applied_rate' => 1000,
            'status' => 'pending',
        ]);

        $this->partnerService->updateCommissionStatus($commission, 'paid', $admin->id, 'Monthly payout');

        $this->assertEquals('paid', $commission->fresh()->status);
        $this->assertNotNull($commission->fresh()->paid_at);
        $this->assertDatabaseHas('partner_audit_logs', [
            'admin_id' => $admin->id,
            'auditable_type' => Commission::class,
            'auditable_id' => (string) $commission->id,
            'event' => 'commission_status_change',
            'reason' => 'Monthly payout',
        ]);
    }

    public function test_self_service_trial_attributes_partner()
    {
        $user = User::factory()->create();
        $partner = Partner::create([
            'user_id' => $user->id,
            'referral_code' => 'GROWTH2026',
        ]);

        $response = $this->postJson('/api/v1/trial/signup', [
            'email' => 'founder@test.com',
            'company' => 'Test Growth Co',
            'referral_code' => 'GROWTH2026',
        ]);

        $response->assertStatus(201);
        $companyId = $response->json('data.company.id');
        $company = Company::find($companyId);

        $this->assertEquals($partner->id, $company->referrer_partner_id);
    }

    public function test_partner_cannot_access_other_partners_stats()
    {
        $user1 = User::factory()->create();
        $partner1 = Partner::create(['user_id' => $user1->id, 'referral_code' => 'P1']);

        $user2 = User::factory()->create();
        $partner2 = Partner::create(['user_id' => $user2->id, 'referral_code' => 'P2']);

        // Authenticate as Partner 1 using the correct guard
        $this->actingAs($user1, 'user_api');

        $response = $this->getJson('/api/v1/partner/stats');
        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('stats.total_conversions'));
    }

    public function test_anti_auto_referral_on_company_created_event()
    {
        $user = User::factory()->create(['email' => 'partner@test.com']);
        $partner = Partner::create([
            'user_id' => $user->id,
            'referral_code' => 'P1',
            'status' => 'active'
        ]);

        // Mock the cookie
        $this->withCookie('leopardo_referrer_id', (string) $partner->id);

        $company = Company::factory()->create([
            'email' => 'partner@test.com' // Same as partner email
        ]);

        event(new \App\Events\CompanyCreated($company));

        $this->assertNull($company->fresh()->referrer_partner_id);
    }

    public function test_manual_referral_code_takes_precedence_over_cookie()
    {
        $partner1 = Partner::create(['user_id' => User::factory()->create()->id, 'referral_code' => 'MANUAL', 'status' => 'active']);
        $partner2 = Partner::create(['user_id' => User::factory()->create()->id, 'referral_code' => 'COOKIE', 'status' => 'active']);

        // Set cookie for partner 2
        $this->withCookie('leopardo_referrer_id', (string) $partner2->id);

        // Pre-attribute to partner 1 (manual code simulation in Controller)
        $company = Company::factory()->create(['referrer_partner_id' => $partner1->id]);

        // Trigger the listener
        event(new \App\Events\CompanyCreated($company));

        // Should still be partner 1
        $this->assertEquals($partner1->id, $company->fresh()->referrer_partner_id);
    }

    public function test_commission_calculation_on_ht_base()
    {
        $user = User::factory()->create();
        $partner = Partner::create([
            'user_id' => $user->id,
            'referral_code' => 'HT_TEST',
            'default_commission_rate' => 1000, // 10%
            'tax_rate' => 2000, // 20% TVA
        ]);

        $company = Company::factory()->create(['referrer_partner_id' => $partner->id]);

        $payment = Payment::create([
            'invoice_id' => 1,
            'company_id' => $company->id,
            'amount' => 120.00, // 100 HT + 20 TVA
            'currency' => 'EUR',
            'status' => 'completed',
        ]);

        $commission = $this->commissionService->recordCommissionForPayment($payment);

        $this->assertNotNull($commission);
        // Payment: 120.00 TTC (12000 cents)
        // HT base = 12000 / 1.2 = 10000 cents
        // Commission = 10% of 10000 = 1000 cents (10.00 EUR)
        $this->assertEquals(1000, $commission->amount);
        $this->assertEquals(10000, $commission->net_amount);
    }

    public function test_payout_request_validation()
    {
        $user = User::factory()->create();
        $partner = Partner::create([
            'user_id' => $user->id,
            'referral_code' => 'P1',
            'payout_threshold' => 5000, // 50.00
        ]);

        // Mock 100.00 earned
        Commission::create([
            'partner_id' => $partner->id,
            'company_id' => '00000000-0000-0000-0000-000000000001',
            'payment_id' => 1,
            'amount' => 10000,
            'applied_rate' => 1000,
            'status' => 'approved',
        ]);

        // 1. Request too much
        try {
            $this->partnerService->requestPayout($partner, 15000, 'EUR');
            $this->fail("Should have thrown DomainException for balance");
        } catch (\App\Exceptions\DomainException $e) {
            $this->assertEquals("INSUFFICIENT_BALANCE", $e->errorCode());
        }

        // 2. Request below threshold
        try {
            $this->partnerService->requestPayout($partner, 1000, 'EUR');
            $this->fail("Should have thrown DomainException for threshold");
        } catch (\App\Exceptions\DomainException $e) {
            $this->assertEquals("BELOW_PAYOUT_THRESHOLD", $e->errorCode());
        }
    }

    public function test_payout_status_change_is_audited()
    {
        $admin = User::factory()->create();
        $partner = Partner::create(['user_id' => User::factory()->create()->id, 'referral_code' => 'P1']);
        $payout = \App\Models\PartnerPayoutRequest::create([
            'partner_id' => $partner->id,
            'amount' => 5000,
            'currency' => 'EUR',
            'status' => 'pending'
        ]);

        $this->partnerService->updatePayoutStatus($payout, 'paid', $admin->id, 'Bank transfer sent');

        $this->assertEquals('paid', $payout->fresh()->status);
        $this->assertDatabaseHas('partner_audit_logs', [
            'admin_id' => $admin->id,
            'event' => 'payout_status_change',
            'reason' => 'Bank transfer sent',
        ]);
    }

    public function test_partner_approval_is_audited()
    {
        $admin = User::factory()->create();
        $user = User::factory()->create();
        $partner = $this->partnerService->apply($user->id, ['type' => 'agency']);

        $this->partnerService->approve($partner, $admin->id);

        $this->assertEquals('approved', $partner->fresh()->application_status);
        $this->assertDatabaseHas('partner_audit_logs', [
            'admin_id' => $admin->id,
            'event' => 'application_approved',
        ]);
    }

    public function test_commission_period_limit_of_12_months()
    {
        $user = User::factory()->create();
        $partner = Partner::create(['user_id' => $user->id, 'referral_code' => 'P1', 'status' => 'active']);
        $company = Company::factory()->create(['referrer_partner_id' => $partner->id]);

        // Referral created 13 months ago
        \App\Models\PartnerReferral::create([
            'partner_id' => $partner->id,
            'company_id' => $company->id,
            'referred_at' => now()->subMonths(13),
        ]);

        $payment = Payment::create([
            'invoice_id' => 1,
            'company_id' => $company->id,
            'amount' => 100,
            'status' => 'completed',
        ]);

        $commission = $this->commissionService->recordCommissionForPayment($payment);

        $this->assertNull($commission, "Should not record commission after 12 months");
    }

    public function test_suspended_partner_cannot_receive_new_clicks()
    {
        $user = User::factory()->create();
        $partner = Partner::create(['user_id' => $user->id, 'referral_code' => 'SUSPENDED', 'status' => 'suspended']);
        $link = \App\Models\PartnerLink::create(['partner_id' => $partner->id, 'code' => 'SUSPENDED', 'is_active' => true]);

        $response = $this->get('/p/SUSPENDED');

        // Middleware should redirect to signup but without setting the cookie
        $response->assertRedirect('/signup');
        $response->assertCookieMissing('leopardo_referrer_id');
    }
}
