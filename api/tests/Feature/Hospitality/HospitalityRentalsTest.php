<?php

declare(strict_types=1);

namespace Tests\Feature\Hospitality;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HospitalityManager\Domain\Models\HospitalityLease;
use App\Modules\HospitalityManager\Domain\Models\HospitalityProperty;
use App\Modules\HospitalityManager\Domain\Models\HospitalityRentPayment;
use App\Modules\HospitalityManager\Domain\Models\HospitalityUnit;
use App\Modules\HospitalityManager\Infrastructure\Services\HospitalityRentService;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-32 HOSPITALITY (HOSP-005, #7947) — gestion locative.
 *
 * Couvre les quatre critères d'acceptation de l'issue :
 *   1. unicité d'un bail actif par unité (index partiel, historique préservé) ;
 *   2. génération des périodes IDEMPOTENTE ;
 *   3. encaissement PARTIEL (cumul, statuts due → partial → paid) ;
 *   4. calcul du retard (`late` dérivé, jamais saisi).
 *
 * Plus les invariants de bord que l'issue ne nomme pas mais qu'un bail
 * implique : `billing_day` ramené au dernier jour du mois, refus du
 * dépassement de dû, bail terminal immuable, étanchéité cross-tenant.
 */
class HospitalityRentalsTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Company $otherCompany;

    private Employee $admin;

    private Employee $lambda;

    private HospitalityProperty $property;

    private HospitalityUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'FR', 'currency' => 'EUR']);
        $company->setFeature('hospitality', true);
        $company->save();
        $this->company = $company;

        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $otherCompany->setFeature('hospitality', true);
        $otherCompany->save();
        $this->otherCompany = $otherCompany;

        $this->admin = $this->manager($this->company);
        $this->lambda = $this->employee($this->company);

        $this->property = $this->createProperty($this->company);
        $this->unit = $this->createUnit($this->property, 'APP-01');
    }

    private function manager(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        return $employee;
    }

    private function employee(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
            'role' => 'employee',
        ]);

        return $employee;
    }

    private function createProperty(Company $company): HospitalityProperty
    {
        /** @var HospitalityProperty $property */
        $property = HospitalityProperty::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Résidence Test',
            'code' => 'RES-'.fake()->unique()->numerify('####'),
            'type' => 'residence',
            'country' => 'FR',
            'currency' => 'EUR',
        ]);

        return $property;
    }

    private function createUnit(HospitalityProperty $property, string $code): HospitalityUnit
    {
        /** @var HospitalityUnit $unit */
        $unit = HospitalityUnit::query()->withoutGlobalScopes()->create([
            'company_id' => $property->company_id,
            'property_id' => $property->getKey(),
            'room_type_id' => null,
            'code' => $code,
            'status' => 'available',
        ]);

        return $unit;
    }

    /** @param array<string, mixed> $overrides */
    private function makeLease(array $overrides = []): HospitalityLease
    {
        /** @var HospitalityLease $lease */
        $lease = HospitalityLease::query()->withoutGlobalScopes()->create(array_merge([
            'company_id' => $this->company->id,
            'property_id' => $this->property->getKey(),
            'unit_id' => $this->unit->getKey(),
            'tenant_name' => 'Awa Ndiaye',
            'starts_on' => '2026-01-01',
            'ends_on' => null,
            'monthly_rent_minor' => 90000,
            'currency' => 'EUR',
            'billing_day' => 5,
            'status' => HospitalityLease::STATUS_ACTIVE,
            'version' => 0,
        ], $overrides));

        return $lease;
    }

    /** @param array<string, mixed> $overrides */
    private function leasePayload(array $overrides = []): array
    {
        return array_merge([
            'unit_id' => $this->unit->getKey(),
            'tenant_name' => 'Moussa Diop',
            'contact_email' => 'moussa@example.com',
            'starts_on' => '2026-02-01',
            'monthly_rent_minor' => 75000,
            'currency' => 'EUR',
            'billing_day' => 10,
        ], $overrides);
    }

    // ── Gate & RBAC ───────────────────────────────────────────────────────

    public function test_leases_require_authentication(): void
    {
        $this->getJson('/api/v1/hospitality/properties/'.$this->property->getKey().'/leases')
            ->assertStatus(401);
    }

    public function test_leases_return_403_when_solution_disabled(): void
    {
        /** @var Company $disabled */
        $disabled = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        Sanctum::actingAs($this->manager($disabled));

        $this->getJson('/api/v1/hospitality/properties/'.$this->property->getKey().'/leases')
            ->assertStatus(403);
    }

    public function test_lambda_cannot_create_lease(): void
    {
        Sanctum::actingAs($this->lambda);

        $this->postJson('/api/v1/hospitality/properties/'.$this->property->getKey().'/leases', $this->leasePayload())
            ->assertStatus(403);
    }

    // ── Critère 1 : unicité du bail actif ─────────────────────────────────

    public function test_only_one_active_lease_per_unit(): void
    {
        $this->makeLease();

        // Le second bail actif sur la MÊME unité doit être refusé par l'index
        // unique partiel, pas seulement par une règle applicative.
        //
        // ⚠ Le refus est provoqué DANS UN SAVEPOINT. Sous PostgreSQL, toute
        // erreur avorte la transaction COURANTE (25P02) : sans savepoint, la
        // violation attendue empoisonne la transaction du test et `tearDown`
        // échoue sur son `SET search_path` — le test SUIVANT du fichier casse
        // alors pour une raison sans rapport. Le `expectException` ne protège
        // de rien : il ne fait qu'attraper l'exception.
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::transaction(function (): void {
            $this->makeLease(['starts_on' => '2026-06-01']);
        });
    }

    public function test_terminated_lease_frees_the_unit_for_a_new_one(): void
    {
        $first = $this->makeLease();

        Sanctum::actingAs($this->admin);
        $this->postJson('/api/v1/hospitality/leases/'.$first->getKey().'/terminate', [
            'status' => HospitalityLease::STATUS_TERMINATED,
            'terminated_on' => '2026-05-31',
        ])->assertOk()->assertJsonPath('data.status', 'terminated');

        // L'index est PARTIEL (`WHERE status='active'`) : l'historique reste,
        // et une nouvelle location devient possible sur la même unité.
        $this->makeLease(['starts_on' => '2026-06-01']);

        $this->assertSame(
            2,
            HospitalityLease::query()->withoutGlobalScopes()->where('unit_id', $this->unit->getKey())->count()
        );
        $this->assertSame(
            1,
            HospitalityLease::query()->withoutGlobalScopes()
                ->where('unit_id', $this->unit->getKey())
                ->where('status', HospitalityLease::STATUS_ACTIVE)
                ->count()
        );
    }

    public function test_terminating_a_terminal_lease_is_rejected(): void
    {
        $lease = $this->makeLease(['status' => HospitalityLease::STATUS_ENDED]);

        Sanctum::actingAs($this->admin);
        $this->postJson('/api/v1/hospitality/leases/'.$lease->getKey().'/terminate', [
            'status' => HospitalityLease::STATUS_TERMINATED,
        ])->assertStatus(409)->assertJsonPath('error', 'INVALID_LEASE_TRANSITION');
    }

    // ── Critère 2 : génération idempotente ────────────────────────────────

    public function test_generate_periods_is_idempotent(): void
    {
        $lease = $this->makeLease(['starts_on' => '2026-01-01', 'billing_day' => 5]);

        Sanctum::actingAs($this->admin);

        $first = $this->postJson('/api/v1/hospitality/leases/'.$lease->getKey().'/generate-periods', ['until' => '2026-03'])
            ->assertOk()
            ->json('data');
        $this->assertSame(3, $first['created']);
        $this->assertSame(0, $first['skipped']);

        $second = $this->postJson('/api/v1/hospitality/leases/'.$lease->getKey().'/generate-periods', ['until' => '2026-03'])
            ->assertOk()
            ->json('data');
        // Rejouer ne crée AUCUN doublon : l'idempotence est portée par
        // UNIQUE(lease_id, period), pas par une vérification applicative.
        $this->assertSame(0, $second['created']);
        $this->assertSame(3, $second['skipped']);

        $this->assertSame(
            3,
            HospitalityRentPayment::query()->withoutGlobalScopes()->where('lease_id', $lease->getKey())->count()
        );
    }

    public function test_generation_does_not_rewrite_an_already_paid_period(): void
    {
        $lease = $this->makeLease(['starts_on' => '2026-01-01']);

        Sanctum::actingAs($this->admin);
        $this->postJson('/api/v1/hospitality/leases/'.$lease->getKey().'/generate-periods', ['until' => '2026-02'])
            ->assertOk();

        /** @var HospitalityRentPayment $january */
        $january = HospitalityRentPayment::query()->withoutGlobalScopes()
            ->where('lease_id', $lease->getKey())->where('period', '2026-01')->firstOrFail();

        $this->postJson('/api/v1/hospitality/rent-payments/'.$january->getKey().'/record', ['amount_minor' => 30000])
            ->assertOk();

        // Un second passage ne doit pas remettre le montant payé à zéro.
        $this->postJson('/api/v1/hospitality/leases/'.$lease->getKey().'/generate-periods', ['until' => '2026-02'])
            ->assertOk();

        $january->refresh();
        $this->assertSame(30000, $january->paid_amount_minor);
        $this->assertSame(HospitalityRentPayment::STATUS_PARTIAL, $january->status);
    }

    public function test_generation_stops_at_lease_end_date(): void
    {
        $lease = $this->makeLease(['starts_on' => '2026-01-01', 'ends_on' => '2026-02-28']);

        Sanctum::actingAs($this->admin);
        $result = $this->postJson('/api/v1/hospitality/leases/'.$lease->getKey().'/generate-periods', ['until' => '2026-06'])
            ->assertOk()
            ->json('data');

        // Un bail qui s'arrête fin février ne produit pas d'échéance de mars :
        // facturer un logement qu'on n'occupe plus est un impayé fabriqué.
        $this->assertSame(2, $result['created']);
    }

    public function test_billing_day_is_clamped_to_last_day_of_month(): void
    {
        $lease = $this->makeLease(['starts_on' => '2026-01-01', 'billing_day' => 31]);

        Sanctum::actingAs($this->admin);
        $this->postJson('/api/v1/hospitality/leases/'.$lease->getKey().'/generate-periods', ['until' => '2026-02'])
            ->assertOk();

        /** @var HospitalityRentPayment $february */
        $february = HospitalityRentPayment::query()->withoutGlobalScopes()
            ->where('lease_id', $lease->getKey())->where('period', '2026-02')->firstOrFail();

        // Février n'a pas de 31 : l'échéance tombe le 28, elle ne glisse JAMAIS
        // au 3 mars (sinon le locataire serait « en retard » sur un jour qui
        // n'a jamais existé).
        $this->assertSame('2026-02-28', $february->due_on?->toDateString());
    }

    // ── Critère 3 : encaissement partiel ──────────────────────────────────

    public function test_partial_payments_accumulate_and_settle_the_period(): void
    {
        $lease = $this->makeLease(['starts_on' => '2026-01-01', 'monthly_rent_minor' => 100000]);

        Sanctum::actingAs($this->admin);
        $this->postJson('/api/v1/hospitality/leases/'.$lease->getKey().'/generate-periods', ['until' => '2026-01'])
            ->assertOk();

        /** @var HospitalityRentPayment $payment */
        $payment = HospitalityRentPayment::query()->withoutGlobalScopes()
            ->where('lease_id', $lease->getKey())->firstOrFail();

        $this->postJson('/api/v1/hospitality/rent-payments/'.$payment->getKey().'/record', ['amount_minor' => 40000])
            ->assertOk()
            ->assertJsonPath('data.status', 'partial')
            ->assertJsonPath('data.paid_amount_minor', 40000)
            ->assertJsonPath('data.outstanding_minor', 60000);

        // Deuxième encaissement : le cumul s'AJOUTE (40000 + 30000 ≠ 30000).
        $this->postJson('/api/v1/hospitality/rent-payments/'.$payment->getKey().'/record', ['amount_minor' => 30000])
            ->assertOk()
            ->assertJsonPath('data.paid_amount_minor', 70000)
            ->assertJsonPath('data.outstanding_minor', 30000);

        $this->postJson('/api/v1/hospitality/rent-payments/'.$payment->getKey().'/record', ['amount_minor' => 30000])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.outstanding_minor', 0);
    }

    public function test_overpayment_is_refused_not_truncated(): void
    {
        $lease = $this->makeLease(['starts_on' => '2026-01-01', 'monthly_rent_minor' => 50000]);

        Sanctum::actingAs($this->admin);
        $this->postJson('/api/v1/hospitality/leases/'.$lease->getKey().'/generate-periods', ['until' => '2026-01'])
            ->assertOk();

        /** @var HospitalityRentPayment $payment */
        $payment = HospitalityRentPayment::query()->withoutGlobalScopes()
            ->where('lease_id', $lease->getKey())->firstOrFail();

        // Accepter 60000 sur 50000 créerait un crédit de 10000 que rien ne
        // restitue : on refuse plutôt que de tronquer silencieusement.
        $this->postJson('/api/v1/hospitality/rent-payments/'.$payment->getKey().'/record', ['amount_minor' => 60000])
            ->assertStatus(422)
            ->assertJsonPath('error', 'RENT_AMOUNT_EXCEEDS_OUTSTANDING');

        $this->assertSame(0, $payment->refresh()->paid_amount_minor);
    }

    public function test_paying_a_settled_period_is_refused(): void
    {
        $lease = $this->makeLease(['starts_on' => '2026-01-01', 'monthly_rent_minor' => 50000]);

        Sanctum::actingAs($this->admin);
        $this->postJson('/api/v1/hospitality/leases/'.$lease->getKey().'/generate-periods', ['until' => '2026-01'])
            ->assertOk();

        /** @var HospitalityRentPayment $payment */
        $payment = HospitalityRentPayment::query()->withoutGlobalScopes()
            ->where('lease_id', $lease->getKey())->firstOrFail();

        $this->postJson('/api/v1/hospitality/rent-payments/'.$payment->getKey().'/record', ['amount_minor' => 50000])
            ->assertOk()->assertJsonPath('data.status', 'paid');

        $this->postJson('/api/v1/hospitality/rent-payments/'.$payment->getKey().'/record', ['amount_minor' => 1000])
            ->assertStatus(422)
            ->assertJsonPath('error', 'RENT_ALREADY_SETTLED');
    }

    // ── Critère 4 : calcul du retard ──────────────────────────────────────

    public function test_late_is_derived_from_the_due_date(): void
    {
        $lease = $this->makeLease(['starts_on' => '2026-01-01', 'billing_day' => 5]);

        /** @var HospitalityRentPayment $overdue */
        $overdue = HospitalityRentPayment::query()->withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'lease_id' => $lease->getKey(),
            'property_id' => $this->property->getKey(),
            'unit_id' => $this->unit->getKey(),
            'period' => '2026-01',
            'due_on' => '2026-01-05',
            'amount_minor' => 90000,
            'paid_amount_minor' => 0,
            'currency' => 'EUR',
            'status' => HospitalityRentPayment::STATUS_DUE,
        ]);

        $service = app(HospitalityRentService::class);

        // Échéance dépassée et non soldée → `late`.
        $service->refreshLateStatus($overdue, \Illuminate\Support\Carbon::parse('2026-02-01'));
        $this->assertSame(HospitalityRentPayment::STATUS_LATE, $overdue->refresh()->status);

        // Un encaissement partiel sur une échéance en retard NE la sort pas du
        // retard : le reste dû est toujours en retard (`late → partial` est
        // autorisé par la machine à états, la dérivation le remet ensuite).
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/v1/hospitality/rent-payments/'.$overdue->getKey().'/record', ['amount_minor' => 10000])
            ->assertOk();

        $service->refreshLateStatus($overdue->refresh(), \Illuminate\Support\Carbon::parse('2026-02-01'));
        $this->assertSame(HospitalityRentPayment::STATUS_LATE, $overdue->refresh()->status);
        $this->assertSame(80000, $overdue->outstandingMinor());
    }

    public function test_index_reflects_late_status_without_a_scheduled_run(): void
    {
        $lease = $this->makeLease(['starts_on' => '2026-01-01']);

        /** @var HospitalityRentPayment $overdue */
        $overdue = HospitalityRentPayment::query()->withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'lease_id' => $lease->getKey(),
            'property_id' => $this->property->getKey(),
            'unit_id' => $this->unit->getKey(),
            'period' => '2020-01',
            'due_on' => '2020-01-05',
            'amount_minor' => 90000,
            'paid_amount_minor' => 0,
            'currency' => 'EUR',
            'status' => HospitalityRentPayment::STATUS_DUE,
        ]);

        Sanctum::actingAs($this->admin);

        // La lecture recalcule `late` : sinon l'écran de relance afficherait
        // un impayé de 2020 comme « à échoir » jusqu'au passage de la cron.
        $this->getJson('/api/v1/hospitality/rent-payments?lease_id='.$lease->getKey())
            ->assertOk()
            ->assertJsonPath('data.0.status', 'late');

        $this->assertSame(HospitalityRentPayment::STATUS_LATE, $overdue->refresh()->status);
    }

    // ── Étanchéité & immuabilité ──────────────────────────────────────────

    public function test_lease_of_another_tenant_is_not_reachable(): void
    {
        $foreignProperty = $this->createProperty($this->otherCompany);
        $foreignUnit = $this->createUnit($foreignProperty, 'APP-X');

        /** @var HospitalityLease $foreign */
        $foreign = HospitalityLease::query()->withoutGlobalScopes()->create([
            'company_id' => $this->otherCompany->id,
            'property_id' => $foreignProperty->getKey(),
            'unit_id' => $foreignUnit->getKey(),
            'tenant_name' => 'Autre Locataire',
            'starts_on' => '2026-01-01',
            'monthly_rent_minor' => 50000,
            'currency' => 'XAF',
            'billing_day' => 1,
            'status' => HospitalityLease::STATUS_ACTIVE,
            'version' => 0,
        ]);

        Sanctum::actingAs($this->admin);

        // 404, pas 403 : on ne révèle pas l'existence d'un bail d'autrui.
        $this->getJson('/api/v1/hospitality/leases/'.$foreign->getKey())->assertStatus(404);
    }

    public function test_terminal_lease_cannot_be_updated(): void
    {
        $lease = $this->makeLease(['status' => HospitalityLease::STATUS_TERMINATED, 'terminated_on' => '2026-03-31']);

        Sanctum::actingAs($this->admin);
        $this->patchJson('/api/v1/hospitality/leases/'.$lease->getKey(), $this->leasePayload(['unit_id' => $this->unit->getKey()]))
            ->assertStatus(422);
    }

    public function test_lease_creation_via_api(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/hospitality/properties/'.$this->property->getKey().'/leases', $this->leasePayload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.tenant_name', 'Moussa Diop')
            ->assertJsonPath('data.billing_day', 10);

        $this->assertSame(
            1,
            HospitalityLease::query()->withoutGlobalScopes()->where('company_id', $this->company->id)->count()
        );
    }

    public function test_unit_of_another_property_is_refused_on_creation(): void
    {
        $otherProperty = $this->createProperty($this->company);
        $foreignUnit = $this->createUnit($otherProperty, 'APP-77');

        Sanctum::actingAs($this->admin);

        // Un bail rattaché à l'établissement A ne peut pas pointer l'unité de
        // l'établissement B : l'occupation fuiterait d'un bien à l'autre.
        $this->postJson('/api/v1/hospitality/properties/'.$this->property->getKey().'/leases', $this->leasePayload([
            'unit_id' => $foreignUnit->getKey(),
        ]))->assertStatus(422);
    }
}
