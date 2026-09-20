<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Billing\Domain\Models\TenantPaymentProfile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #7727 (BC-21 BILLING) — profils de paiement du tenant : clés PSP propres /
 * IBAN / mobile money + routage des encaissements Accounting.
 *
 * Contrat verrouillé ici :
 *   1. CRUD + activation par le manager principal, secrets chiffrés au repos
 *      et JAMAIS renvoyés en clair (masques) ;
 *   2. RBAC : un manager non principal est refusé (403) ;
 *   3. isolation cross-tenant : le tenant B ne voit ni ne modifie les profils
 *      du tenant A (liste vide, 404 sur accès direct) ;
 *   4. routage : un checkout Accounting d'un tenant avec profil `stripe_keys`
 *      ACTIF part avec la clé DU TENANT (Bearer) et trace le profil dans les
 *      métadonnées ;
 *   5. fallback : sans profil actif, comportement plateforme inchangé.
 */
class TenantPaymentProfileApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(string $country = 'FR', string $currency = 'EUR'): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => $country, 'currency' => $currency]);

        return $company;
    }

    private function principal(Company $company, string $managerRole = 'principal'): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => $managerRole,
        ]);

        return $manager;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function createProfilePayload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'stripe_keys',
            'label' => 'Compte Stripe restaurant',
            'secrets' => [
                'secret_key' => 'sk_live_tenant_own_key_4242',
                'webhook_secret' => 'whsec_tenant_own_9999',
            ],
        ], $overrides);
    }

    public function test_principal_creates_activates_and_lists_profiles_with_masked_secrets(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->principal($company));

        $created = $this->postJson('/api/v1/billing/payment-profiles', $this->createProfilePayload());
        $created->assertCreated();
        $created->assertJsonPath('data.type', 'stripe_keys');
        $created->assertJsonPath('data.status', 'draft');
        $created->assertJsonPath('data.secrets.secret_key.configured', true);
        $created->assertJsonPath('data.secrets.secret_key.mask', 'sk_live_••••4242');

        $raw = $created->getContent();
        $this->assertIsString($raw);
        $this->assertStringNotContainsString('sk_live_tenant_own_key_4242', $raw);

        // Chiffré au repos : la colonne ne contient jamais le clair.
        $id = (int) $created->json('data.id');
        $row = DB::table('tenant_payment_profiles')->where('id', $id)->first();
        $this->assertNotNull($row);
        $this->assertStringNotContainsString('sk_live_tenant_own_key_4242', (string) $row->secrets);
        $this->assertSame((string) $company->id, (string) $row->company_id);

        // Activation : le profil devient actif et par défaut.
        $activated = $this->postJson("/api/v1/billing/payment-profiles/{$id}/activate");
        $activated->assertOk();
        $activated->assertJsonPath('data.status', 'active');
        $activated->assertJsonPath('data.is_default', true);

        // Liste : masques uniquement.
        $list = $this->getJson('/api/v1/billing/payment-profiles');
        $list->assertOk();
        $this->assertCount(1, $list->json('data.items'));
        $listRaw = $list->getContent();
        $this->assertIsString($listRaw);
        $this->assertStringNotContainsString('sk_live_tenant_own_key_4242', $listRaw);
    }

    public function test_update_is_write_only_and_deletion_works(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->principal($company));

        $id = (int) $this->postJson('/api/v1/billing/payment-profiles', $this->createProfilePayload())
            ->json('data.id');

        // PUT sans secret : la clé en place est conservée (write-only).
        $this->putJson("/api/v1/billing/payment-profiles/{$id}", [
            'label' => 'Compte renommé',
            'secrets' => ['secret_key' => ''],
        ])->assertOk()->assertJsonPath('data.label', 'Compte renommé');

        /** @var TenantPaymentProfile $profile */
        $profile = TenantPaymentProfile::query()->withoutGlobalScopes()->findOrFail($id);
        $this->assertSame('sk_live_tenant_own_key_4242', ($profile->secrets ?? [])['secret_key'] ?? null);

        $this->deleteJson("/api/v1/billing/payment-profiles/{$id}")->assertNoContent();
        $this->assertNull(TenantPaymentProfile::query()->withoutGlobalScopes()->find($id));
    }

    public function test_cash_profile_requires_no_secret_and_can_be_activated(): void
    {
        // #7863 : encaissement AU LOCAL (espèces / TPE au comptoir) — aucun
        // secret à configurer, métadonnée optionnelle `location`, activation
        // possible dès la création (pas de clé à vérifier).
        $company = $this->company();
        Sanctum::actingAs($this->principal($company));

        $created = $this->postJson('/api/v1/billing/payment-profiles', [
            'type' => 'cash',
            'label' => 'Caisse comptoir',
            'details' => ['location' => 'Restaurant — comptoir principal'],
        ]);
        $created->assertCreated();
        $created->assertJsonPath('data.type', 'cash');
        $created->assertJsonPath('data.status', 'draft');
        $created->assertJsonPath('data.details.location', 'Restaurant — comptoir principal');
        $this->assertSame([], (array) $created->json('data.secrets'));

        $id = (int) $created->json('data.id');
        $activated = $this->postJson("/api/v1/billing/payment-profiles/{$id}/activate");
        $activated->assertOk();
        $activated->assertJsonPath('data.status', 'active');
        $activated->assertJsonPath('data.is_default', true);
    }

    public function test_non_principal_manager_is_forbidden(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->principal($company, 'comptable'));

        $this->getJson('/api/v1/billing/payment-profiles')->assertForbidden();
        $this->postJson('/api/v1/billing/payment-profiles', $this->createProfilePayload())->assertForbidden();
    }

    public function test_cross_tenant_isolation_on_list_and_direct_access(): void
    {
        $companyA = $this->company();
        Sanctum::actingAs($this->principal($companyA));
        $id = (int) $this->postJson('/api/v1/billing/payment-profiles', $this->createProfilePayload())
            ->json('data.id');

        $companyB = $this->company();
        Sanctum::actingAs($this->principal($companyB));

        $list = $this->getJson('/api/v1/billing/payment-profiles');
        $list->assertOk();
        $this->assertCount(0, $list->json('data.items'));

        // Accès direct : 404 (jamais 403 — l'existence n'est pas révélée).
        $this->putJson("/api/v1/billing/payment-profiles/{$id}", ['label' => 'pirate'])->assertNotFound();
        $this->postJson("/api/v1/billing/payment-profiles/{$id}/activate")->assertNotFound();
        $this->deleteJson("/api/v1/billing/payment-profiles/{$id}")->assertNotFound();
    }

    // ── Routage des encaissements Accounting ────────────────────────────────

    /**
     * @return AccountingDocument
     */
    private function invoice(Company $company): AccountingDocument
    {
        /** @var AccountingContact $contact */
        $contact = AccountingContact::create([
            'company_id' => $company->id,
            'type' => 'customer',
            'name' => 'Client Routage',
            'email' => 'client@exemple.fr',
        ]);

        /** @var AccountingDocument $document */
        $document = AccountingDocument::create([
            'company_id' => $company->id,
            'type' => 'invoice',
            'number' => 'FAC-2026-7727',
            'status' => 'sent',
            'contact_id' => $contact->id,
            'issue_date' => '2026-09-01',
            'due_date' => '2026-09-15',
            'currency' => 'EUR',
            'subtotal_ht' => 100.0,
            'tax_amount' => 20.0,
            'total_ttc' => 120.0,
            'tva_rate' => 20.0,
            'paid_amount' => 0.0,
        ]);

        return $document;
    }

    public function test_accounting_checkout_uses_tenant_stripe_keys_and_traces_profile(): void
    {
        Config::set('services.stripe.secret', 'sk_live_PLATFORM_key_0000');

        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_tenant_001',
                'url' => 'https://checkout.stripe.com/c/pay/cs_test_tenant_001',
                'expires_at' => time() + 3600,
            ], 200),
        ]);

        $company = $this->company('FR', 'EUR');
        Sanctum::actingAs($this->principal($company));

        $profileId = (int) $this->postJson('/api/v1/billing/payment-profiles', $this->createProfilePayload())
            ->json('data.id');
        $this->postJson("/api/v1/billing/payment-profiles/{$profileId}/activate")->assertOk();

        app()->instance('current_company', $company);
        $invoice = $this->invoice($company);
        app()->forgetInstance('current_company');

        $response = $this->postJson('/api/v1/accounting/documents/'.$invoice->id.'/checkout');

        $response->assertOk();
        $response->assertJsonPath('data.gateway', 'stripe');

        Http::assertSent(function ($request) use ($profileId): bool {
            return $request->url() === 'https://api.stripe.com/v1/checkout/sessions'
                // La clé DU TENANT, pas celle de la plateforme.
                && $request->header('Authorization')[0] === 'Bearer sk_live_tenant_own_key_4242'
                // Traçabilité : le profil utilisé est dans les métadonnées
                // (formulaire Stripe à clés plates).
                && ($request['metadata[payment_profile_id]'] ?? null) === (string) $profileId;
        });
    }

    public function test_accounting_checkout_falls_back_to_platform_keys_without_active_profile(): void
    {
        Config::set('services.stripe.secret', 'sk_live_PLATFORM_key_0000');

        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_platform_001',
                'url' => 'https://checkout.stripe.com/c/pay/cs_test_platform_001',
                'expires_at' => time() + 3600,
            ], 200),
        ]);

        $company = $this->company('FR', 'EUR');
        Sanctum::actingAs($this->principal($company));

        app()->instance('current_company', $company);
        $invoice = $this->invoice($company);
        app()->forgetInstance('current_company');

        $response = $this->postJson('/api/v1/accounting/documents/'.$invoice->id.'/checkout');

        $response->assertOk();
        $response->assertJsonPath('data.gateway', 'stripe');

        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return $request->header('Authorization')[0] === 'Bearer sk_live_PLATFORM_key_0000'
                && ! isset($payload['metadata[payment_profile_id]']);
        });
    }
}
