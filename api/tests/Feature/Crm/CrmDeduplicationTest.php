<?php

declare(strict_types=1);

namespace Tests\Feature\Crm;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\CRM\Domain\Models\CrmAccount;
use App\Modules\CRM\Domain\Models\CrmContact;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #5718 — Déduplication et fusion SUPERVISÉE.
 *
 * Tests écrits avant l'implémentation (DoD) : suggestions explicables sans
 * fuite cross-tenant, preview sans écriture, permission élevée (principal),
 * audit + conservation (le perdant est archivé, jamais supprimé), rollback
 * logique possible.
 */
class CrmDeduplicationTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Company $otherCompany;

    private Employee $principal;

    private Employee $rh;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createCrmTables();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->otherCompany = $other;

        /** @var Employee $principal */
        $principal = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->principal = $principal;

        /** @var Employee $rh */
        $rh = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'rh',
        ]);
        $this->rh = $rh;
    }

    protected function tearDown(): void
    {
        $this->dropCrmTables();
        parent::tearDown();
    }

    public function test_suggestions_detect_same_email(): void
    {
        Sanctum::actingAs($this->principal);

        $this->createAccount('Acme', 'Acme@Example.COM');
        $this->createAccount('Acme Services', 'acme@example.com');

        $response = $this->getJson('/api/v1/crm/dedup/suggestions?entity=accounts')
            ->assertStatus(200);

        $suggestions = $response->json('data');
        self::assertCount(1, $suggestions);
        self::assertSame('same_email', $suggestions[0]['reason']);
        self::assertSame(0.95, $suggestions[0]['score']);
    }

    public function test_suggestions_detect_similar_name(): void
    {
        Sanctum::actingAs($this->principal);

        $this->createAccount('Global Services', null);
        $this->createAccount('Global Service', null);

        $response = $this->getJson('/api/v1/crm/dedup/suggestions?entity=accounts')
            ->assertStatus(200);

        $suggestions = $response->json('data');
        self::assertCount(1, $suggestions);
        self::assertSame('similar_name', $suggestions[0]['reason']);
    }

    public function test_suggestions_never_leak_cross_tenant(): void
    {
        Sanctum::actingAs($this->principal);

        $this->createAccount('Acme', 'acme@example.com');

        // Même email dans l'AUTRE tenant : ne doit JAMAIS apparaître.
        CrmAccount::query()->create([
            'company_id' => $this->otherCompany->id,
            'name' => 'Acme (autre tenant)',
            'email' => 'acme@example.com',
            'status' => 'active',
        ]);
        CrmAccount::query()->create([
            'company_id' => $this->otherCompany->id,
            'name' => 'Acme', // même nom aussi
            'status' => 'active',
        ]);

        $this->getJson('/api/v1/crm/dedup/suggestions?entity=accounts')
            ->assertStatus(200)
            ->assertJsonPath('data', []);
    }

    public function test_preview_does_not_write(): void
    {
        Sanctum::actingAs($this->principal);

        $winner = $this->createAccount('Acme', null);
        $loser = $this->createAccount('Acme Corp', 'acme@example.com');

        $this->getJson('/api/v1/crm/merge/preview?entity=accounts&winner_id='.$winner->id.'&loser_id='.$loser->id)
            ->assertStatus(200)
            ->assertJsonPath('data.will_archive_loser', true)
            ->assertJsonPath('data.field_updates.0.field', 'email');

        // Aucune écriture : le perdant n'est pas archivé, l'email du gagnant inchangé.
        self::assertNull($loser->refresh()->archived_at);
        self::assertNull($winner->refresh()->email);
    }

    public function test_merge_transfers_contacts_archives_loser_and_audits(): void
    {
        Sanctum::actingAs($this->principal);

        $winner = $this->createAccount('Acme', null);
        $loser = $this->createAccount('Acme Corp', 'acme@example.com');

        $contact1 = $this->createContact($loser->id, 'Jean', 'Dupont');
        $this->createContact($loser->id, 'Marie', 'Martin');

        $this->postJson('/api/v1/crm/merge', [
            'entity' => 'accounts',
            'winner_id' => $winner->id,
            'loser_id' => $loser->id,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.updated_fields', ['email'])
            ->assertJsonPath('data.transferred_contacts', 2)
            ->assertJsonPath('data.archived_loser', true);

        // Champs fusionnés + relations transférées + perdant archivé.
        self::assertSame('acme@example.com', $winner->refresh()->email);
        self::assertNotNull($loser->refresh()->archived_at);
        self::assertSame($winner->id, $contact1->refresh()->account_id);
        self::assertSame(2, CrmContact::query()->where('account_id', $winner->id)->count());

        // Audit de fusion avec historique.
        self::assertSame(1, AuditLog::query()->where('action', 'crm.merge.accounts')->count());
        $audit = AuditLog::query()->where('action', 'crm.merge.accounts')->firstOrFail();
        self::assertSame($loser->id, $audit->old_values['loser_id']);
        self::assertSame($winner->id, $audit->new_values['winner_id']);
    }

    public function test_merge_requires_principal_permission(): void
    {
        Sanctum::actingAs($this->rh);

        $winner = $this->createAccount('Acme', null);
        $loser = $this->createAccount('Acme Corp', 'acme@example.com');

        // rh peut voir les suggestions mais PAS fusionner (permission élevée).
        $this->getJson('/api/v1/crm/dedup/suggestions?entity=accounts')->assertStatus(200);
        $this->postJson('/api/v1/crm/merge', [
            'entity' => 'accounts',
            'winner_id' => $winner->id,
            'loser_id' => $loser->id,
        ])->assertStatus(403);
    }

    public function test_merge_rejects_same_entity(): void
    {
        Sanctum::actingAs($this->principal);

        $account = $this->createAccount('Acme', null);

        $this->postJson('/api/v1/crm/merge', [
            'entity' => 'accounts',
            'winner_id' => $account->id,
            'loser_id' => $account->id,
        ])->assertStatus(422);
    }

    public function test_merge_cross_tenant_returns_404(): void
    {
        Sanctum::actingAs($this->principal);

        $winner = $this->createAccount('Acme', null);

        /** @var Employee $otherPrincipal */
        $otherPrincipal = Employee::factory()->create([
            'company_id' => $this->otherCompany->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        Sanctum::actingAs($otherPrincipal);

        $this->postJson('/api/v1/crm/merge', [
            'entity' => 'accounts',
            'winner_id' => $winner->id,
            'loser_id' => $winner->id + 1,
        ])->assertStatus(404);
    }

    public function test_suggestions_are_bounded(): void
    {
        Sanctum::actingAs($this->principal);

        for ($i = 0; $i < 3; $i++) {
            $this->createAccount("Company {$i}", "same@example.com");
        }

        $this->getJson('/api/v1/crm/dedup/suggestions?entity=accounts&limit=2')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function createAccount(string $name, ?string $email): CrmAccount
    {
        /** @var CrmAccount $account */
        $account = CrmAccount::query()->create([
            'company_id' => $this->company->id,
            'name' => $name,
            'email' => $email,
            'status' => 'active',
        ]);

        return $account;
    }

    private function createContact(int $accountId, string $first, string $last): CrmContact
    {
        /** @var CrmContact $contact */
        $contact = CrmContact::query()->create([
            'company_id' => $this->company->id,
            'account_id' => $accountId,
            'first_name' => $first,
            'last_name' => $last,
        ]);

        return $contact;
    }

    private function createCrmTables(): void
    {
        if (! Schema::hasTable('crm_accounts')) {
            Schema::create('crm_accounts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->string('name', 255);
                $table->string('status', 20)->default('active');
                $table->unsignedBigInteger('owner_id')->nullable();
                $table->text('email')->nullable();
                $table->string('phone', 60)->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('crm_contacts')) {
            Schema::create('crm_contacts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('account_id')->nullable();
                $table->string('first_name', 100);
                $table->string('last_name', 100);
                $table->text('email')->nullable();
                $table->string('phone', 60)->nullable();
                $table->string('title', 100)->nullable();
                $table->string('status', 20)->default('active');
                $table->unsignedBigInteger('owner_id')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->boolean('opt_in_email')->default(false);
                $table->boolean('opt_in_sms')->default(false);
                $table->boolean('opt_in_whatsapp')->default(false);
                $table->text('notes')->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();
            });
        }
    }

    private function dropCrmTables(): void
    {
        Schema::dropIfExists('crm_contacts');
        Schema::dropIfExists('crm_accounts');
    }
}
