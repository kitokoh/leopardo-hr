<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\CRM\Application\Services\CrmOverdueReminderService;
use App\Modules\CRM\Domain\Models\CrmAccount;
use App\Modules\CRM\Domain\Models\CrmContact;
use App\Modules\CRM\Domain\Models\CrmLead;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\Support\CreatesCrmSchema;
use Tests\TestCase;

/**
 * Issue #8871 (sous-lot L4c de #8396) — RBAC de route sur les tâches et garde
 * tenant sur la lecture/écriture unitaire du répertoire CRM.
 *
 * Ces tests verrouillent des DÉFAUTS DE PRODUCTION, pas des contrats
 * théoriques. Chacun échoue sur le code d'avant le correctif :
 *
 *  1. le bloc tâches/timeline (#5720) ne portait pas `api.manager` : tout
 *     employé authentifié atteignait le CRUD (`DELETE` → 200 au lieu de 403) ;
 *  2. `showAccount`/`showContact`/`showLead`/`updateLead` n'avaient aucune
 *     garde tenant : `GET` d'un autre tenant → 200 avec les données, et `PUT`
 *     permettait de MODIFIER un lead d'un autre tenant ;
 *  3. `CrmOverdueReminderService` castait un UUID en int (→ 0) et lisait
 *     `assignee_id` sur une projection qui ne sélectionnait que `assigned_to` :
 *     aucune relance n'était émise ;
 *  4. `StoreCrmLeadRequest` exigeait `first_name` aussi au `PUT` → 422 avant la
 *     garde tenant, masquant le 404 documenté.
 *
 * Les assertions portent sur l'état OBSERVABLE (code HTTP, lignes réellement
 * écrites en base) — jamais sur la présence d'un appel dans le code.
 */
class CrmRbacTenantGuardTest extends TestCase
{
    use CreatesCrmSchema;
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createCrmSchemaIfMissing();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $this->companyB = $companyB;
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');

        parent::tearDown();
    }

    private function manager(Company $company, string $managerRole = 'principal'): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => $managerRole,
            'status' => 'active',
        ]);

        return $manager;
    }

    private function ordinaryEmployee(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        return $employee;
    }

    /** @param array<string, mixed> $overrides */
    private function lead(Company $company, array $overrides = []): CrmLead
    {
        /** @var CrmLead $lead */
        $lead = CrmLead::query()->create(array_merge([
            'company_id' => $company->id,
            'first_name' => 'Sofia',
            'last_name' => 'Merabet',
            'company_name' => 'Startup X',
            'email' => 'sofia@startup-x.dz',
            'source' => 'manual',
            'status' => 'new',
        ], $overrides));

        return $lead;
    }

    /** @param array<string, mixed> $overrides */
    private function account(Company $company, array $overrides = []): CrmAccount
    {
        /** @var CrmAccount $account */
        $account = CrmAccount::query()->create(array_merge([
            'company_id' => $company->id,
            'name' => 'Acme Algérie',
            'status' => 'active',
        ], $overrides));

        return $account;
    }

    /** @param array<string, mixed> $overrides */
    private function contact(Company $company, array $overrides = []): CrmContact
    {
        /** @var CrmContact $contact */
        $contact = CrmContact::query()->create(array_merge([
            'company_id' => $company->id,
            'first_name' => 'Karim',
            'last_name' => 'Benali',
            'email' => 'karim@acme.dz',
            'status' => 'active',
        ], $overrides));

        return $contact;
    }

    // ── Défaut 1 — RBAC de route sur les tâches (#5720) ──────────────────────

    public function test_assignee_employee_cannot_reach_task_crud(): void
    {
        // Le cas réellement vulnérable : un employé ASSIGNÉ à la tâche. La
        // Policy `CrmTaskPolicy` l'autorise explicitement (`view`/`update`
        // renvoient true pour `assigned_to === actor->id`), donc la Policy ne
        // peut PAS servir de garde : seul le middleware de route `api.manager`
        // arrête la requête. Avant #8871, ce bloc n'en portait aucun et
        // l'assigné atteignait tout le CRUD — y compris `DELETE`, que la Policy
        // refuse mais qui n'était jamais atteint faute de garde en amont.
        $assignee = $this->ordinaryEmployee($this->companyA);

        $taskId = $this->createCrmTask([
            'company_id' => $this->companyA->id,
            'assigned_to' => $assignee->id,
        ]);

        Sanctum::actingAs($assignee);

        $this->getJson("/api/v1/crm/tasks/{$taskId}")->assertStatus(403);
        $this->patchJson("/api/v1/crm/tasks/{$taskId}", ['title' => 'Hack'])->assertStatus(403);
        $this->deleteJson("/api/v1/crm/tasks/{$taskId}")->assertStatus(403);
        $this->getJson('/api/v1/crm/tasks')->assertStatus(403);
        $this->postJson('/api/v1/crm/tasks', ['title' => 'X'])->assertStatus(403);
    }

    public function test_non_crm_manager_role_is_forbidden_on_tasks(): void
    {
        Sanctum::actingAs($this->manager($this->companyA, 'comptable'));

        // `comptable` est manager mais hors des rôles CRM (CrmTaskPolicy::isCrmRole).
        $this->getJson('/api/v1/crm/tasks')->assertStatus(403);
        $this->postJson('/api/v1/crm/tasks', ['title' => 'X'])->assertStatus(403);
    }

    public function test_employee_cannot_reach_account_timeline(): void
    {
        $manager = $this->manager($this->companyA);
        Sanctum::actingAs($manager);
        $accountId = $this->createCrmAccount(['company_id' => $this->companyA->id]);

        Sanctum::actingAs($this->ordinaryEmployee($this->companyA));

        $this->getJson("/api/v1/crm/accounts/{$accountId}/timeline")->assertStatus(403);
    }

    public function test_crm_manager_still_reaches_task_crud(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        // Garde anti-sur-restriction : le correctif ne doit pas casser le
        // parcours nominal du manager CRM.
        $this->postJson('/api/v1/crm/tasks', ['title' => 'Relancer Alpha'])
            ->assertStatus(201);

        $this->getJson('/api/v1/crm/tasks')->assertOk();
    }

    // ── Défaut 2 — garde tenant sur la lecture/écriture unitaire ─────────────

    public function test_cross_tenant_unitary_reads_are_404(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $foreignAccount = $this->account($this->companyB);
        $foreignContact = $this->contact($this->companyB);
        $foreignLead = $this->lead($this->companyB);

        // Avant #8871 : 200 avec les données de l'autre tenant.
        $this->getJson('/api/v1/crm/accounts/'.(int) $foreignAccount->getAttribute('id'))
            ->assertStatus(404);
        $this->getJson('/api/v1/crm/contacts/'.(int) $foreignContact->getAttribute('id'))
            ->assertStatus(404);
        $this->getJson('/api/v1/crm/leads/'.(int) $foreignLead->getAttribute('id'))
            ->assertStatus(404);
    }

    public function test_cross_tenant_lead_update_is_404_and_leaves_the_row_untouched(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $foreignLead = $this->lead($this->companyB, ['status' => 'new']);
        $foreignId = (int) $foreignLead->getAttribute('id');

        $this->putJson("/api/v1/crm/leads/{$foreignId}", ['status' => 'qualified'])
            ->assertStatus(404);

        // Le code HTTP seul ne prouve pas l'absence d'écriture : on relit la
        // ligne BRUTE en base (hors scope tenant) pour vérifier qu'elle n'a pas
        // bougé. Avant #8871, le PUT modifiait réellement le lead d'autrui.
        $row = DB::table('crm_leads')->where('id', $foreignId)->first();

        $this->assertNotNull($row);
        $this->assertSame('new', $row->status);
    }

    public function test_own_tenant_unitary_reads_still_work(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $ownLead = $this->lead($this->companyA);

        // Garde anti-sur-restriction : la garde ne doit pas bloquer son propre tenant.
        $this->getJson('/api/v1/crm/leads/'.(int) $ownLead->getAttribute('id'))->assertOk();
    }

    // ── Défaut 3 — relances : UUID du tenant + assigné réel ──────────────────

    public function test_overdue_reminder_notifies_the_assignee_of_the_right_tenant(): void
    {
        $assignee = $this->ordinaryEmployee($this->companyA);

        $taskId = $this->createCrmTask([
            'company_id' => $this->companyA->id,
            'assigned_to' => $assignee->id,
            'due_at' => now()->subDay(),
            'status' => 'todo',
        ]);

        $emitted = app(CrmOverdueReminderService::class)->run();

        $this->assertSame(1, $emitted, 'La relance doit être émise une fois.');

        $notification = DB::table('notifications')
            ->where('employee_id', $assignee->id)
            ->where('type', 'crm_task_overdue')
            ->first();

        $this->assertNotNull($notification, 'Aucune notification créée pour l\'assigné.');

        // Avant #8871 : `company_id` valait 0 (UUID casté en int) — la
        // notification visait un tenant inexistant. On vérifie l'UUID réel.
        $this->assertSame(
            (string) $this->companyA->id,
            (string) $notification->company_id,
            'La notification doit porter l\'UUID du tenant de la tâche, pas 0.'
        );

        $this->assertSame(
            $taskId,
            (int) json_decode((string) $notification->data, true)['task_id'],
            'La notification doit référencer la tâche relancée.'
        );
    }

    public function test_overdue_reminder_skips_tasks_without_assignee(): void
    {
        $this->createCrmTask([
            'company_id' => $this->companyA->id,
            'assigned_to' => null,
            'due_at' => now()->subDay(),
            'status' => 'todo',
        ]);

        // La relance est émise (ligne crm_task_reminders) mais le listener ne
        // crée aucune notification : il n'y a personne à notifier.
        $this->assertSame(1, app(CrmOverdueReminderService::class)->run());
        $this->assertSame(0, DB::table('notifications')->where('type', 'crm_task_overdue')->count());
    }

    // ── Défaut 4 — PUT partiel : 404 documenté, plus de 422 masquant ─────────

    public function test_lead_update_accepts_a_partial_payload(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $lead = $this->lead($this->companyA);
        $id = (int) $lead->getAttribute('id');

        // Avant #8871 : 422 (`first_name` requis au PUT) — la mise à jour
        // partielle d'un lead de son propre tenant était impossible.
        $this->putJson("/api/v1/crm/leads/{$id}", ['status' => 'qualified'])
            ->assertOk()
            ->assertJsonPath('data.status', 'qualified');

        $this->assertSame('qualified', DB::table('crm_leads')->where('id', $id)->value('status'));
    }

    public function test_lead_creation_still_requires_first_name(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        // Garde anti-sur-restriction : `first_name` reste obligatoire à la création.
        $this->postJson('/api/v1/crm/leads', ['last_name' => 'Sans Prenom'])
            ->assertStatus(422);
    }
}
