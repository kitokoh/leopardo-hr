<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\CRM\Domain\Models\CrmAccount;
use App\Modules\CRM\Domain\Models\CrmActivity;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #eval-2026-11-23 — `GET|POST /v1/crm/activities` (journal d'activites tenant).
 *
 * La matrice de contrat (CRM_API_MATRICE_TENANT_PLATFORM §2.5) documentait ces
 * deux routes, mais seule la timeline par account existait : `/crm/activities`
 * repondait 404. Ce test verrouille le contrat HTTP :
 *
 *  - `POST` cree une activite rattachee a une entite metier (201) ;
 *  - `POST` sans ancrage metier -> 422 (une activite orpheline n'apparait
 *    dans aucune timeline) ;
 *  - `GET` liste, filtre par type et par account, trie par `occurred_at` desc ;
 *  - append-only : aucune route PUT/PATCH/DELETE n'est exposee ;
 *  - RBAC : un employe lambda recoit 403 ;
 *  - isolation tenant : les activites d'une autre societe ne sortent jamais.
 */
class CrmActivitiesEndpointTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Company $other;

    private Employee $principal;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $this->other = $other;

        /** @var Employee $principal */
        $principal = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        $this->principal = $principal;
    }

    public function test_post_creates_activity_anchored_on_account(): void
    {
        $account = $this->account($this->company);

        Sanctum::actingAs($this->principal);

        $response = $this->postJson('/api/v1/crm/activities', [
            'type' => CrmActivity::TYPE_CALL,
            'subject' => 'Appel de qualification',
            'description' => 'Le client veut un devis traiteur pour 40 couverts.',
            'account_id' => $account->id,
            'occurred_at' => '2026-11-20T10:30:00Z',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', CrmActivity::TYPE_CALL)
            ->assertJsonPath('data.subject', 'Appel de qualification');

        $this->assertDatabaseHas('crm_activities', [
            'company_id' => $this->company->id,
            'account_id' => $account->id,
            'type' => CrmActivity::TYPE_CALL,
            'created_by' => $this->principal->id,
        ]);
    }

    public function test_post_without_business_anchor_is_rejected(): void
    {
        Sanctum::actingAs($this->principal);

        $this->postJson('/api/v1/crm/activities', [
            'type' => CrmActivity::TYPE_NOTE,
            'subject' => 'Note orpheline',
        ])->assertStatus(422)
            ->assertJsonPath('error', 'VALIDATION_ERROR');
    }

    public function test_post_rejects_unknown_type(): void
    {
        $account = $this->account($this->company);

        Sanctum::actingAs($this->principal);

        $this->postJson('/api/v1/crm/activities', [
            'type' => 'ping',
            'account_id' => $account->id,
        ])->assertStatus(422);
    }

    public function test_index_lists_tenant_activities_newest_first_and_filters(): void
    {
        $account = $this->account($this->company);
        $otherAccount = $this->account($this->company, 'Autre compte');

        $this->activity($this->company, $account, CrmActivity::TYPE_CALL, '2026-11-01T09:00:00Z');
        $this->activity($this->company, $account, CrmActivity::TYPE_MEETING, '2026-11-15T09:00:00Z');
        $this->activity($this->company, $otherAccount, CrmActivity::TYPE_EMAIL, '2026-11-10T09:00:00Z');

        // Activite d'un AUTRE tenant : ne doit jamais apparaitre.
        $foreignAccount = $this->account($this->other);
        $this->activity($this->other, $foreignAccount, CrmActivity::TYPE_CALL, '2026-11-20T09:00:00Z');

        Sanctum::actingAs($this->principal);

        $all = $this->getJson('/api/v1/crm/activities');
        $all->assertOk();
        $this->assertSame(3, $all->json('meta.total'));
        $this->assertSame(CrmActivity::TYPE_MEETING, $all->json('data.0.type'));

        $byAccount = $this->getJson('/api/v1/crm/activities?account_id='.$account->id);
        $byAccount->assertOk();
        $this->assertSame(2, $byAccount->json('meta.total'));

        $byType = $this->getJson('/api/v1/crm/activities?type='.CrmActivity::TYPE_EMAIL);
        $byType->assertOk();
        $this->assertSame(1, $byType->json('meta.total'));
        $this->assertSame($otherAccount->id, $byType->json('data.0.account_id'));

        // Seule l'activite du 15/11 tombe dans la fenetre (01/11 et 10/11 avant).
        $byDate = $this->getJson('/api/v1/crm/activities?from=2026-11-12&to=2026-11-30');
        $byDate->assertOk();
        $this->assertSame(1, $byDate->json('meta.total'));
        $this->assertSame(CrmActivity::TYPE_MEETING, $byDate->json('data.0.type'));
    }

    public function test_index_rejects_bad_date_format(): void
    {
        Sanctum::actingAs($this->principal);

        $this->getJson('/api/v1/crm/activities?from=15/11/2026')->assertStatus(422);
    }

    public function test_employee_cannot_read_or_write_activities(): void
    {
        $account = $this->account($this->company);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
            'manager_role' => null,
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/crm/activities')->assertForbidden();
        $this->postJson('/api/v1/crm/activities', [
            'type' => CrmActivity::TYPE_NOTE,
            'account_id' => $account->id,
        ])->assertForbidden();
    }

    public function test_journal_is_append_only(): void
    {
        $account = $this->account($this->company);
        $activity = $this->activity($this->company, $account, CrmActivity::TYPE_NOTE, '2026-11-05T09:00:00Z');

        Sanctum::actingAs($this->principal);

        // Aucune route de mutation n'est exposee : 404 (route absente) ou
        // 405 (verbe non supporte), jamais 200.
        $this->assertContains(
            $this->putJson('/api/v1/crm/activities/'.$activity->id, ['subject' => 'Reecrit'])->status(),
            [404, 405],
        );
        $this->assertContains(
            $this->deleteJson('/api/v1/crm/activities/'.$activity->id)->status(),
            [404, 405],
        );

        $this->assertDatabaseHas('crm_activities', [
            'id' => $activity->id,
            'subject' => 'Note initiale',
        ]);
    }

    private function account(Company $company, string $name = 'Restaurant Chez Leo'): CrmAccount
    {
        /** @var CrmAccount $account */
        $account = CrmAccount::query()->create([
            'company_id' => $company->id,
            'name' => $name,
            'status' => 'active',
        ]);

        return $account;
    }

    private function activity(Company $company, CrmAccount $account, string $type, string $occurredAt): CrmActivity
    {
        /** @var CrmActivity $activity */
        $activity = CrmActivity::query()->create([
            'company_id' => $company->id,
            'account_id' => $account->id,
            'type' => $type,
            'subject' => $type === CrmActivity::TYPE_NOTE ? 'Note initiale' : 'Sujet '.$type,
            'occurred_at' => $occurredAt,
        ]);

        return $activity;
    }
}
