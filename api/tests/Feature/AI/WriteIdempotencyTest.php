<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\AI\IntentEngine;
use App\AI\PendingActionStore;
use App\AI\ToolRegistry;
use App\AI\WriteIdempotencyStore;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Domain\Models\AbsenceType;
use Database\Seeders\AIToolRegistrySeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * BOS-032 (#8222) — idempotence MÉTIER des write-tools IA.
 *
 * Le PendingActionStore (one-shot, TTL) protège du double-clic, mais deux
 * chemins produisaient un SECOND effet pour une MÊME intention confirmée :
 *  1. retry réseau sur le même pending_action_id (après consommation du
 *     pending one-shot, le client retentait → 404, puis re-proposition →
 *     nouvelle exécution) ;
 *  2. reconfirmation : une nouvelle proposition de la même intention dans la
 *     même conversation, confirmée → second effet (deux absences identiques,
 *     deux annonces…).
 *
 * Ces tests verrouillent les critères d'acceptation de l'issue :
 *  - double confirmation (retry réseau simulé) → UN SEUL effet, sur les deux
 *    writes critiques nommés (`create_employee`, `absence_decision`) ;
 *  - rejeu = retour du résultat initial AVEC marqueur `idempotent_replay` ;
 *  - le backend `database` du PendingActionStore est one-shot, isolé par
 *    tenant et par utilisateur, et respecte le TTL.
 */
class WriteIdempotencyTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        config(['ai.enabled' => true]);
        $this->seed(AIToolRegistrySeeder::class);
        $this->app->forgetInstance(ToolRegistry::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    /**
     * @return array{0: Company, 1: Employee}
     */
    private function fixture(): array
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $principal */
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);

        app()->instance('current_company', $company);

        return [$company, $principal];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function storePending(Company $company, Employee $actor, string $tool, array $arguments, ?int $conversationId = null): string
    {
        return app(PendingActionStore::class)->store(
            (string) $company->id,
            (int) $actor->id,
            $tool,
            $arguments,
            $conversationId,
        );
    }

    private function conversationId(Company $company, Employee $actor): int
    {
        return (int) DB::table('ai_conversations')->insertGetId([
            'company_id' => (string) $company->id,
            'user_id' => (int) $actor->id,
            'title' => 'Fil de test idempotence',
            'messages' => '[]',
            'context' => '{}',
            'token_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_double_confirmation_of_the_same_pending_creates_one_employee_only(): void
    {
        [$company, $principal] = $this->fixture();
        Sanctum::actingAs($principal);

        $arguments = [
            'first_name' => 'Amina',
            'last_name' => 'Zerrouki',
            'email' => 'amina@example.test',
        ];

        $pendingId = $this->storePending($company, $principal, 'create_employee', $arguments);

        $first = $this->postJson("/api/v1/ai/actions/{$pendingId}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'executed')
            ->assertJsonPath('data.tool', 'create_employee');

        $employeeId = $first->json('data.result.employee_id');
        $this->assertNotNull($employeeId);

        // Retry réseau simulé : MÊME pending_action_id, après consommation.
        $this->postJson("/api/v1/ai/actions/{$pendingId}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'executed')
            ->assertJsonPath('data.result.idempotent_replay', true)
            ->assertJsonPath('data.result.employee_id', $employeeId);

        $this->assertSame(1, Employee::query()
            ->where('company_id', $company->id)
            ->where('email', 'amina@example.test')
            ->count(), 'une intention confirmée deux fois = un seul effet');
    }

    public function test_double_confirmation_of_absence_approval_replays_the_initial_result(): void
    {
        [$company, $principal] = $this->fixture();
        Sanctum::actingAs($principal);

        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        /** @var AbsenceType $type */
        $type = AbsenceType::factory()->nonDeductible()->create(['company_id' => $company->id]);
        /** @var Absence $absence */
        $absence = Absence::factory()->withType($type)->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-12',
            'days_count' => 3,
            'status' => 'pending',
            'reason' => 'Congé familial',
        ]);

        $pendingId = $this->storePending($company, $principal, 'absence_decision', [
            'absence_id' => $absence->id,
            'decision' => 'approve',
        ]);

        $this->postJson("/api/v1/ai/actions/{$pendingId}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'executed')
            ->assertJsonPath('data.result.absence_id', $absence->id)
            ->assertJsonPath('data.result.status', 'approved');

        // Sans idempotence, la seconde confirmation repartirait dans
        // l'Action canonique → ABSENCE_NOT_PENDING (preuve d'un 2e effet
        // tenté). Avec le store, on doit recevoir le résultat INITIAL.
        $this->postJson("/api/v1/ai/actions/{$pendingId}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'executed')
            ->assertJsonPath('data.result.idempotent_replay', true)
            ->assertJsonPath('data.result.absence_id', $absence->id)
            ->assertJsonPath('data.result.status', 'approved');

        $this->assertSame('approved', $absence->refresh()->status);
    }

    public function test_reconfirmation_in_the_same_conversation_replays_without_a_second_effect(): void
    {
        [$company, $principal] = $this->fixture();
        Sanctum::actingAs($principal);

        $conversationId = $this->conversationId($company, $principal);
        $arguments = [
            'first_name' => 'Amina',
            'last_name' => 'Zerrouki',
            'email' => 'amina@example.test',
        ];

        // Même intention, DEUX propositions distinctes (le LLM re-propose
        // après le retry côté client), même conversation, mêmes arguments.
        $firstPending = $this->storePending($company, $principal, 'create_employee', $arguments, $conversationId);
        $secondPending = $this->storePending($company, $principal, 'create_employee', $arguments, $conversationId);

        $first = $this->postJson("/api/v1/ai/actions/{$firstPending}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'executed');

        $this->postJson("/api/v1/ai/actions/{$secondPending}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'executed')
            ->assertJsonPath('data.result.idempotent_replay', true)
            ->assertJsonPath('data.result.employee_id', $first->json('data.result.employee_id'));

        $this->assertSame(1, Employee::query()
            ->where('company_id', $company->id)
            ->where('email', 'amina@example.test')
            ->count(), 'même conversation + mêmes arguments = une seule intention');
    }

    public function test_distinct_conversations_are_distinct_intentions(): void
    {
        [$company, $principal] = $this->fixture();
        Sanctum::actingAs($principal);

        /** @var AbsenceType $type */
        $type = AbsenceType::factory()->nonDeductible()->create(['company_id' => $company->id]);

        $arguments = [
            'start_date' => '2026-05-04',
            'end_date' => '2026-05-05',
            'type' => $type->code,
        ];

        // Deux conversations différentes : deux intentions légitimes, deux
        // effets (l'utilisateur peut vraiment poser deux fois la même
        // absence) — la clé d'idempotence ne doit PAS fusionner.
        foreach ([1, 2] as $index) {
            $pendingId = $this->storePending(
                $company,
                $principal,
                'create_absence',
                $arguments,
                $this->conversationId($company, $principal),
            );

            $this->postJson("/api/v1/ai/actions/{$pendingId}/confirm")
                ->assertOk()
                ->assertJsonPath('data.status', 'executed')
                ->assertJsonMissingPath('data.result.idempotent_replay');
        }

        $this->assertSame(2, Absence::query()
            ->where('company_id', $company->id)
            ->where('employee_id', $principal->id)
            ->count());
    }

    public function test_database_pending_action_store_is_one_shot_and_tenant_isolated(): void
    {
        [$company, $principal] = $this->fixture();
        config(['ai.pending_action_store' => 'database']);

        $store = app(PendingActionStore::class);
        $pendingId = $store->store((string) $company->id, (int) $principal->id, 'notify_team', ['title' => 'Test']);

        // Isolation tenant : un autre company_id ne voit pas l'action ET ne
        // la consomme pas.
        $this->assertNull($store->pull($pendingId, '00000000-0000-0000-0000-000000000000', (int) $principal->id));

        /** @var array<string, mixed>|null $payload */
        $payload = $store->pull($pendingId, (string) $company->id, (int) $principal->id);
        $this->assertNotNull($payload);
        $this->assertSame('notify_team', $payload['tool']);
        $this->assertSame(['title' => 'Test'], $payload['arguments']);

        // One-shot : consommée, elle n'est plus lisible.
        $this->assertNull($store->pull($pendingId, (string) $company->id, (int) $principal->id));
    }

    public function test_database_pending_action_store_honours_the_ttl(): void
    {
        [$company, $principal] = $this->fixture();
        config(['ai.pending_action_store' => 'database']);

        $store = app(PendingActionStore::class);
        $pendingId = $store->store((string) $company->id, (int) $principal->id, 'notify_team', ['title' => 'Expiré']);

        DB::table('ai_pending_actions')->where('id', $pendingId)->update(['expires_at' => now()->subMinute()]);

        $this->assertNull($store->pull($pendingId, (string) $company->id, (int) $principal->id));
    }

    public function test_expired_idempotency_entries_are_ignored(): void
    {
        [$company, $principal] = $this->fixture();

        $store = app(WriteIdempotencyStore::class);
        $hash = $store->argumentsHash(['absence_id' => 7, 'decision' => 'approve']);
        $key = $store->makeKey((string) $company->id, 123, null, 'absence_decision', $hash);

        $store->store((string) $company->id, $key, 'absence_decision', $hash, null, 123, ['absence_id' => 7, 'status' => 'approved']);
        $this->assertNotNull($store->find((string) $company->id, $key));

        DB::table('ai_write_idempotency')->where('idempotency_key', $key)->update(['expires_at' => now()->subMinute()]);

        $this->assertNull($store->find((string) $company->id, $key), 'une entrée expirée ne protège plus : nouvelle intention possible');
    }

    public function test_confirmed_write_without_conversation_falls_back_to_pending_scoped_key(): void
    {
        [$company, $principal] = $this->fixture();

        $engine = app(IntentEngine::class);
        $arguments = [
            'first_name' => 'Amina',
            'last_name' => 'Zerrouki',
            'email' => 'amina@example.test',
        ];

        // Sans conversation connue (appel historique), la clé retombe sur le
        // pending_action_id : le retry réseau est couvert, et deux pendings
        // distincts restent deux intentions (comportement dégradé documenté).
        $first = $engine->executeConfirmedWrite('create_employee', $arguments, (string) $company->id, (int) $principal->id, 'pending-a');
        $this->assertArrayNotHasKey('error', $first);

        $replay = $engine->executeConfirmedWrite('create_employee', $arguments, (string) $company->id, (int) $principal->id, 'pending-a');
        $this->assertSame(true, $replay['idempotent_replay'] ?? null);
        $this->assertSame($first['employee_id'], $replay['employee_id']);

        $this->assertSame(1, Employee::query()
            ->where('company_id', $company->id)
            ->where('email', 'amina@example.test')
            ->count());
    }
}
