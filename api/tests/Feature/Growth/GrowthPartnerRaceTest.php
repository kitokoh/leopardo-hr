<?php

declare(strict_types=1);

namespace Tests\Feature\Growth;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Domain\Models\User;
use App\Core\Tenant\Domain\Models\Company;
use App\Exceptions\DomainException;
use App\Modules\Billing\Domain\Models\Partner;
use App\Modules\Billing\Domain\Models\PartnerPayoutRequest;
use App\Modules\Billing\Infrastructure\Services\PartnerService;
use App\Modules\Payroll\Domain\Models\Commission;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * QA expert5 #2999 — races Growth :
 * - double candidature partenaire → 1 seul partner (contrainte unique + 400 propre) ;
 * - demandes de payout concurrentes ne dépassent pas le solde (lock transaction) ;
 * - jamais de message d'exception brut exposé au client.
 */
class GrowthPartnerRaceTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $employee;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;
        /** @var Employee $employee */
        $employee = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $this->employee = $employee;
        Sanctum::actingAs($this->employee);
    }

    public function test_double_apply_is_blocked_with_clean_400(): void
    {
        $payload = ['type' => 'individual'];

        $first = $this->postJson('/api/v1/growth/partner/apply', $payload);
        $first->assertCreated();

        // Seconde candidature (même user) → 400 ALREADY_EXISTS, pas 201, pas de doublon.
        $second = $this->postJson('/api/v1/growth/partner/apply', $payload);
        $second->assertStatus(400)
            ->assertJsonPath('error', 'ALREADY_EXISTS');

        $this->assertSame(1, Partner::where('user_id', $this->employee->id)->count());
    }

    public function test_payout_does_not_expose_raw_exception_message(): void
    {
        $partner = $this->createApprovedPartner(10000); // 100,00 de commissions approuvées

        $response = $this->postJson('/api/v1/growth/partner/payout', [
            'amount' => 999999,
            'currency' => 'DZD',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'INSUFFICIENT_BALANCE')
            ->assertJsonMissing(['message' => 'Solde insuffisant.']);
    }

    public function test_consecutive_payouts_cannot_overdraw_balance(): void
    {
        $partner = $this->createApprovedPartner(10000); // solde disponible 100,00

        $first = $this->postJson('/api/v1/growth/partner/payout', [
            'amount' => 8000,
            'currency' => 'DZD',
        ]);
        $first->assertCreated();

        // Solde restant 20,00 → une seconde demande de 50,00 doit être refusée.
        $second = $this->postJson('/api/v1/growth/partner/payout', [
            'amount' => 5000,
            'currency' => 'DZD',
        ]);
        $second->assertStatus(422)
            ->assertJsonPath('error', 'INSUFFICIENT_BALANCE');

        $this->assertSame(1, PartnerPayoutRequest::where('partner_id', $partner->id)->count());
    }

    public function test_service_apply_is_idempotent_under_unique_constraint(): void
    {
        $service = app(PartnerService::class);
        // PartnerService::apply accepts a public users.id because partners.user_id
        // references users.id; an Employee id belongs to the tenant table.
        $user = User::query()->forceCreate([
            'first_name' => 'Partner',
            'last_name' => 'Idempotency QA',
            'email' => 'partner-idempotency-'.uniqid().'@test.hr',
            'password_hash' => Hash::make('password123'),
        ]);

        $first = $service->apply((int) $user->id, ['type' => 'individual']);
        $this->assertInstanceOf(Partner::class, $first);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Déjà partenaire.');
        $service->apply((int) $user->id, ['type' => 'individual']);
    }

    public function test_payout_status_transition_guard_rejects_invalid_moves(): void
    {
        // #3859 : transitions de statut gardées (allowlist). Un payout déjà
        // payé ne peut pas repasser en pending (sinon le solde disponible le
        // compterait deux fois en attente), et un statut inconnu est refusé.
        $service = app(PartnerService::class);
        $partner = $this->createApprovedPartner(10000);

        $payout = PartnerPayoutRequest::create([
            'partner_id' => $partner->id,
            'amount' => 8000,
            'currency' => 'DZD',
            'status' => 'pending',
        ]);

        // Transition valide : pending → paid
        $service->updatePayoutStatus($payout, 'paid', (int) $this->adminUser->id, 'Paiement effectue');
        $payout->refresh();
        $this->assertSame('paid', $payout->status);

        // Transition invalide : paid → pending → DomainException propre
        try {
            $service->updatePayoutStatus($payout, 'pending', (int) $this->adminUser->id, 'Reouverture');
            $this->fail('La transition paid -> pending doit etre refusee.');
        } catch (DomainException $e) {
            $this->assertSame(422, $e->statusCode());
            $this->assertSame('INVALID_PAYOUT_TRANSITION', $e->errorCode());
        }

        // Le statut n'a pas bougé
        $payout->refresh();
        $this->assertSame('paid', $payout->status);
    }

    private function createApprovedPartner(int $commissionsCents): Partner
    {
        // #PR : partners.user_id → users.id (FK publique) — les employees
        // vivent dans la table employees (tenant), leurs ids ne sont PAS des
        // users.id : créer un vrai User pour satisfaire la FK (23503).
        $user = User::query()->forceCreate([
            'first_name' => 'Partner',
            'last_name' => 'QA',
            'email' => 'partner-'.uniqid().'@test.hr',
            'password_hash' => Hash::make('password123'),
        ]);
        $this->adminUser = $user;
        $partner = Partner::create([
            'user_id' => $user->id,
            'referral_code' => 'QA-'.strtoupper(substr(uniqid(), -6)),
            'application_status' => 'approved',
            'status' => 'active',
            'type' => 'individual',
            'payout_threshold' => 0,
        ]);

        Commission::create([
            'partner_id' => $partner->id,
            'company_id' => $this->company->id,
            'payment_id' => 1,
            'amount' => $commissionsCents,
            'currency' => 'DZD',
            'applied_rate' => 1000,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        return $partner;
    }
}
