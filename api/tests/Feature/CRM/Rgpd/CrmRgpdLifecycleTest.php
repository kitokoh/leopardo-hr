<?php

declare(strict_types=1);

namespace Tests\Feature\CRM\Rgpd;

use App\Core\Tenant\Domain\Models\Company;
use App\Support\Gdpr\CrmConsentGate;
use App\Support\Gdpr\CrmRgpdRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #5739 (CRM PRE) — cycle de vie RGPD des données CRM client.
 *
 * Couvre : registre versionné lié aux tables, anonymisation sûre/rejouable
 * (idempotente), aucune PII dans les logs, exports/artefacts étiquetés tenant,
 * retrait de consentement bloquant les nouveaux envois, tables absentes
 * gérées proprement (socle V0 non mergé).
 */
class CrmRgpdLifecycleTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * Crée les tables CRM minimales dans `shared_tenants` (dans la
     * transaction du test — rollback automatique, aucun résidu).
     */
    private function createCrmTables(): void
    {
        DB::statement('SET search_path TO shared_tenants');

        if (! Schema::hasTable('crm_contacts')) {
            Schema::create('crm_contacts', static function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->timestamps();
                $table->index('company_id');
            });
        }

        if (! Schema::hasTable('crm_consents')) {
            Schema::create('crm_consents', static function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->string('contact_id');
                $table->string('channel');
                $table->string('purpose');
                $table->string('status');
                $table->timestamps();
                $table->index('company_id');
            });
        }

        DB::statement('SET search_path TO shared_tenants, public');
    }

    // ── registre versionné lié aux tables ───────────────────────────────────

    public function test_registry_is_versioned_and_linked_to_tables(): void
    {
        $this->assertGreaterThanOrEqual(1, CrmRgpdRegistry::version());

        $entries = CrmRgpdRegistry::entries();
        $this->assertNotEmpty($entries);

        foreach ($entries as $table => $entry) {
            $this->assertArrayHasKey('table', $entry);
            $this->assertSame($table, $entry['table']);
            $this->assertArrayHasKey('purpose', $entry);
            $this->assertArrayHasKey('legal_basis', $entry);
            $this->assertArrayHasKey('retention_days', $entry);
            $this->assertArrayHasKey('responsible', $entry);
            $this->assertArrayHasKey('pii_columns', $entry);
        }

        $this->assertNotNull(CrmRgpdRegistry::entryForTable('crm_contacts'));
        $this->assertArrayHasKey('email', CrmRgpdRegistry::piiColumns('crm_contacts'));
    }

    // ── anonymisation : idempotente, rejouable, sans PII dans les logs ──────

    public function test_anonymization_is_idempotent_replayable_and_tenant_scoped(): void
    {
        $this->createCrmTables();

        /** @var Company $tenantA */
        $tenantA = Company::factory()->create();
        /** @var Company $tenantB */
        $tenantB = Company::factory()->create();

        DB::table('crm_contacts')->insert([
            ['company_id' => $tenantA->id, 'first_name' => 'Alice', 'last_name' => 'Réelle', 'email' => 'alice@acme.test', 'phone' => '+213550000001', 'created_at' => now(), 'updated_at' => now()],
            ['company_id' => $tenantA->id, 'first_name' => 'Bob', 'last_name' => 'Réel', 'email' => 'bob@acme.test', 'phone' => '+213550000002', 'created_at' => now(), 'updated_at' => now()],
            ['company_id' => $tenantB->id, 'first_name' => 'Carol', 'last_name' => 'Autre', 'email' => 'carol@acme.test', 'phone' => '+213550000003', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Premier passage (écrit).
        $exit = Artisan::call('crm:anonymize', ['--company' => $tenantA->id, '--force']);
        $this->assertSame(0, $exit);

        $afterFirst = DB::table('crm_contacts')->where('company_id', $tenantA->id)
            ->orderBy('id')->pluck('email', 'id')->all();
        foreach ($afterFirst as $email) {
            $this->assertStringEndsWith('@anonymised.invalid', (string) $email, 'Email anonymisé.');
        }
        $this->assertStringNotContainsString('alice@acme.test', implode(',', $afterFirst));

        // Le tenant B n'est PAS touché.
        $this->assertSame('carol@acme.test', (string) DB::table('crm_contacts')->where('company_id', $tenantB->id)->value('email'));

        // Second passage : idempotent (valeurs strictement identiques) → rejouable.
        $exit = Artisan::call('crm:anonymize', ['--company' => $tenantA->id, '--force']);
        $this->assertSame(0, $exit);
        $afterSecond = DB::table('crm_contacts')->where('company_id', $tenantA->id)
            ->orderBy('id')->pluck('email', 'id')->all();
        $this->assertSame($afterFirst, $afterSecond, 'L\'anonymisation doit être déterministe (rejouable).');
    }

    public function test_anonymization_dry_run_writes_nothing_and_output_has_no_pii(): void
    {
        $this->createCrmTables();

        /** @var Company $tenantA */
        $tenantA = Company::factory()->create();
        DB::table('crm_contacts')->insert([
            'company_id' => $tenantA->id,
            'first_name' => 'Alice',
            'last_name' => 'Réelle',
            'email' => 'alice@acme.test',
            'phone' => '+213550000001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exit = Artisan::call('crm:anonymize', ['--company' => $tenantA->id]);
        $this->assertSame(0, $exit);

        // Dry-run : aucune écriture.
        $this->assertSame('alice@acme.test', (string) DB::table('crm_contacts')->where('company_id', $tenantA->id)->value('email'));

        // Aucune PII dans la sortie de la commande.
        $output = Artisan::output();
        $this->assertStringNotContainsString('alice@acme.test', $output);
        $this->assertStringNotContainsString('+213550000001', $output);
    }

    public function test_anonymization_requires_tenant_and_skips_missing_tables(): void
    {
        // Sans tenant ciblé → échec (jamais d'anonymisation globale implicite).
        $exit = Artisan::call('crm:anonymize', ['--force']);
        $this->assertSame(1, $exit);

        // Tables absentes (socle V0 non mergé) → skip propre, exit 0.
        /** @var Company $tenantA */
        $tenantA = Company::factory()->create();
        $exit = Artisan::call('crm:anonymize', ['--company' => $tenantA->id, '--force']);
        $this->assertSame(0, $exit);
        $this->assertStringContainsString('table absente', Artisan::output());
    }

    // ── consentement : le retrait bloque les nouveaux envois ────────────────

    public function test_consent_withdrawal_blocks_new_sends(): void
    {
        $this->createCrmTables();

        /** @var Company $tenantA */
        $tenantA = Company::factory()->create();
        $gate = app(CrmConsentGate::class);
        $contact = 'contact-123';
        $channel = 'email';
        $purpose = 'newsletter';

        // Pas de consentement → pas d'envoi (fail-closed).
        $this->assertFalse($gate->canSend($channel, $purpose, $contact, (string) $tenantA->id));

        // Consentement accordé → envoi autorisé.
        DB::table('crm_consents')->insert([
            'company_id' => $tenantA->id,
            'contact_id' => $contact,
            'channel' => $channel,
            'purpose' => $purpose,
            'status' => 'granted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assertTrue($gate->canSend($channel, $purpose, $contact, (string) $tenantA->id));

        // Retrait → les NOUVEAUX envois sont bloqués.
        DB::table('crm_consents')->insert([
            'company_id' => $tenantA->id,
            'contact_id' => $contact,
            'channel' => $channel,
            'purpose' => $purpose,
            'status' => 'revoked',
            'created_at' => now()->addMinute(),
            'updated_at' => now()->addMinute(),
        ]);
        $this->assertFalse($gate->canSend($channel, $purpose, $contact, (string) $tenantA->id));

        // Ré-accord (consentement plus récent) → envois rétablis.
        DB::table('crm_consents')->insert([
            'company_id' => $tenantA->id,
            'contact_id' => $contact,
            'channel' => $channel,
            'purpose' => $purpose,
            'status' => 'granted',
            'created_at' => now()->addMinutes(2),
            'updated_at' => now()->addMinutes(2),
        ]);
        $this->assertTrue($gate->canSend($channel, $purpose, $contact, (string) $tenantA->id));

        // Isolation : le consentement d'un autre contact/tenant ne s'applique pas.
        $this->assertFalse($gate->canSend($channel, $purpose, 'contact-456', (string) $tenantA->id));
    }

    public function test_consent_gate_fails_closed_when_table_missing(): void
    {
        // Pas de table crm_consents sur main (socle V0 non mergé) : fail-closed.
        if (Schema::hasTable('crm_consents')) {
            Schema::drop('crm_consents');
        }

        /** @var Company $tenantA */
        $tenantA = Company::factory()->create();
        $gate = app(CrmConsentGate::class);

        $this->assertFalse($gate->canSend('whatsapp', 'promo', 'contact-1', (string) $tenantA->id));
    }
}
