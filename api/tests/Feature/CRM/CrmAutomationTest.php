<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\CRM\Domain\Models\CrmAutomation;
use App\Modules\CRM\Domain\Models\CrmAutomationRun;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * CRM V1 — Automatisations (issue #5728).
 *
 * Rules bornées et allowlistées, simulation sans effet, boucles/retry infini
 * empêchés (actions terminales + run_key unique + tentatives plafonnées),
 * run history et dead letter, arrêt d'urgence.
 */
class CrmAutomationTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var \App\Core\Tenant\Domain\Models\Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');

        parent::tearDown();
    }

    private function manager(): \App\Core\Auth\Domain\Models\Employee
    {
        /** @var \App\Core\Auth\Domain\Models\Employee $manager */
        $manager = \App\Core\Auth\Domain\Models\Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        return $manager;
    }

    public function test_automation_can_be_created_and_activated(): void
    {
        Sanctum::actingAs($this->manager());

        $response = $this->postJson('/api/v1/crm/automations', [
            'name' => 'Relance message entrant',
            'trigger_event' => 'crm.message.inbound',
            'conditions' => [
                ['field' => 'data.channel', 'operator' => 'equals', 'value' => 'whatsapp'],
            ],
            'actions' => [
                ['type' => 'send_sms', 'config' => ['body' => 'Merci, nous vous répondons sous 24h.']],
            ],
            'status' => 'active',
        ]);

        $response->assertCreated()->assertJsonPath('data.trigger_event', 'crm.message.inbound');
        $automationId = $response->json('data.id');

        $this->postJson('/api/v1/crm/automations/'.$automationId.'/activate')->assertOk();
        $this->getJson('/api/v1/crm/automations/'.$automationId)->assertJsonPath('data.status', 'active');
    }

    public function test_invalid_trigger_or_action_is_rejected(): void
    {
        Sanctum::actingAs($this->manager());

        $this->postJson('/api/v1/crm/automations', [
            'name' => 'Règle pirate',
            'trigger_event' => 'crm.anything',
            'actions' => [['type' => 'delete_everything']],
        ])->assertStatus(422);

        $this->postJson('/api/v1/crm/automations', [
            'name' => 'Règle valide déclencheur mais action inconnue',
            'trigger_event' => 'crm.message.inbound',
            'actions' => [['type' => 'drop_database']],
        ])->assertStatus(422);
    }

    public function test_dispatch_executes_automation_and_records_run(): void
    {
        config()->set('crm.channels.consent_fallback', 'allow');
        Sanctum::actingAs($this->manager());

        $automation = CrmAutomation::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Webhook sur message sent',
            'trigger_event' => 'crm.message.sent',
            'conditions' => [],
            'actions' => [
                ['type' => 'http_webhook', 'config' => ['url' => 'https://hook.example.com/crm', 'secret' => 's3cret']],
            ],
            'status' => 'active',
        ]);

        Http::fake(['hook.example.com/*' => Http::response(['ok' => true], 200)]);

        $this->postJson('/api/v1/crm/automations/events/crm.message.sent', [
            'context' => [
                'entity_type' => 'channel_message',
                'entity_id' => 'msg-1',
                'data' => ['channel' => 'whatsapp'],
            ],
        ])->assertOk()->assertJsonPath('data.received', true);

        Http::assertSentCount(1);

        $this->assertDatabaseHas('crm_automation_runs', [
            'company_id' => $this->company->id,
            'automation_id' => $automation->id,
            'status' => 'succeeded',
        ]);
    }

    public function test_same_event_on_same_entity_runs_only_once(): void
    {
        Sanctum::actingAs($this->manager());

        $automation = CrmAutomation::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Idempotent',
            'trigger_event' => 'crm.message.inbound',
            'conditions' => [],
            'actions' => [['type' => 'http_webhook', 'config' => ['url' => 'https://hook.example.com/idem']]],
            'status' => 'active',
        ]);

        Http::fake(['hook.example.com/*' => Http::response([], 200)]);

        $context = ['entity_type' => 'channel_message', 'entity_id' => 'msg-42'];
        $this->postJson('/api/v1/crm/automations/events/crm.message.inbound', ['context' => $context])->assertOk();
        $this->postJson('/api/v1/crm/automations/events/crm.message.inbound', ['context' => $context])->assertOk();

        Http::assertSentCount(1);
        $this->assertSame(1, CrmAutomationRun::query()
            ->where('automation_id', $automation->id)
            ->where('status', 'succeeded')
            ->count());
    }

    public function test_simulation_has_no_side_effect(): void
    {
        Sanctum::actingAs($this->manager());

        $automation = CrmAutomation::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Simulation',
            'trigger_event' => 'crm.message.inbound',
            'conditions' => [],
            'actions' => [['type' => 'http_webhook', 'config' => ['url' => 'https://hook.example.com/sim']]],
            'status' => 'active',
        ]);

        Http::fake(['hook.example.com/*' => Http::response([], 200)]);

        $this->postJson('/api/v1/crm/automations/'.$automation->id.'/simulate', [
            'context' => ['entity_type' => 'channel_message', 'entity_id' => 'msg-sim'],
        ])->assertOk()->assertJsonPath('data.matched', true);

        // Aucun appel HTTP (simulation sans effet), mais un run dry_run historisé.
        Http::assertNothingSent();
        $this->assertDatabaseHas('crm_automation_runs', [
            'automation_id' => $automation->id,
            'dry_run' => true,
            'status' => 'succeeded',
        ]);
    }

    public function test_emergency_stop_blocks_dispatch(): void
    {
        Sanctum::actingAs($this->manager());

        CrmAutomation::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Stoppée',
            'trigger_event' => 'crm.message.inbound',
            'conditions' => [],
            'actions' => [['type' => 'http_webhook', 'config' => ['url' => 'https://hook.example.com/stop']]],
            'status' => 'active',
        ]);

        Http::fake();

        $this->postJson('/api/v1/crm/automations/emergency-stop', ['enabled' => false])->assertOk();

        $this->postJson('/api/v1/crm/automations/events/crm.message.inbound', [
            'context' => ['entity_type' => 'channel_message', 'entity_id' => 'msg-1'],
        ])->assertStatus(423)->assertJsonPath('error', 'CRM_AUTOMATION_EMERGENCY_STOPPED');

        Http::assertNothingSent();
    }

    public function test_failing_action_dead_letters_the_run(): void
    {
        Sanctum::actingAs($this->manager());

        $automation = CrmAutomation::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Échec',
            'trigger_event' => 'crm.message.inbound',
            'conditions' => [],
            'actions' => [['type' => 'http_webhook', 'config' => ['url' => 'https://hook.example.com/500']]],
            'status' => 'active',
        ]);

        Http::fake(['hook.example.com/*' => Http::response([], 500)]);

        $this->postJson('/api/v1/crm/automations/events/crm.message.inbound', [
            'context' => ['entity_type' => 'channel_message', 'entity_id' => 'msg-err'],
        ])->assertOk();

        // max_attempts=1 → premier échec = dead-letter (aucun retry infini).
        $this->assertDatabaseHas('crm_automation_runs', [
            'automation_id' => $automation->id,
            'status' => 'dead_lettered',
        ]);
        Http::assertSentCount(1);
    }

    public function test_condition_not_matched_skips_run(): void
    {
        Sanctum::actingAs($this->manager());

        $automation = CrmAutomation::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Condition',
            'trigger_event' => 'crm.message.inbound',
            'conditions' => [
                ['field' => 'data.channel', 'operator' => 'equals', 'value' => 'sms'],
            ],
            'actions' => [['type' => 'http_webhook', 'config' => ['url' => 'https://hook.example.com/cond']]],
            'status' => 'active',
        ]);

        Http::fake();

        $this->postJson('/api/v1/crm/automations/events/crm.message.inbound', [
            'context' => ['entity_type' => 'channel_message', 'entity_id' => 'msg-cond', 'data' => ['channel' => 'whatsapp']],
        ])->assertOk();

        Http::assertNothingSent();
        $this->assertDatabaseHas('crm_automation_runs', [
            'automation_id' => $automation->id,
            'status' => 'skipped',
        ]);
    }

    public function test_other_tenant_automation_is_not_visible(): void
    {
        Sanctum::actingAs($this->manager());

        /** @var \App\Core\Tenant\Domain\Models\Company $otherCompany */
        $otherCompany = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $automation = CrmAutomation::query()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Autre tenant',
            'trigger_event' => 'crm.message.inbound',
            'conditions' => [],
            'actions' => [['type' => 'http_webhook', 'config' => ['url' => 'https://hook.example.com/x']]],
            'status' => 'active',
        ]);

        $this->getJson('/api/v1/crm/automations/'.$automation->id)->assertStatus(404);
        $this->getJson('/api/v1/crm/automations/'.$automation->id.'/runs')->assertStatus(404);
    }
}
