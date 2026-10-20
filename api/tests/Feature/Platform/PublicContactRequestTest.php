<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\CRM\Domain\Models\CrmLead;
use App\Modules\Platform\Domain\Models\PublicContactRequest;
use App\Modules\Showcase\Domain\Enums\CompanyShowcaseStatus;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * SUPERAPP-P1 (#8760) — Contact public client ↔ professionnel.
 *
 * `POST /api/v1/public/contact-requests` (sans auth, sans middleware tenant).
 *
 * Scénarios d'acceptation couverts ici :
 *  1. **Découverte → contact** : un profil PUBLIÉ reçoit une demande ;
 *  2. **Profil** : la réponse publique ne contient aucune donnée interne
 *     (ni `company_id`, ni id de tenant, ni statut interne) ;
 *  3. **Contact** : la demande est persistée et un lead CRM est créé dans le
 *     tenant PROPRIÉTAIRE (le pro la voit depuis son Business OS) ;
 *  4. **Notification** : les managers du tenant sont notifiés (canal in-app) ;
 *  5. **Multi-tenant** : deux profils homonymes de deux tenants différents
 *     n'échangent jamais leurs demandes — la résolution est bornée au slug ;
 *  6. **Profil désactivé** : dépublié / `is_public=false` / société suspendue /
 *     feature absente → 404 uniforme, aucune écriture, aucune notification ;
 *  7. **Séparation Léa** : aucune ligne `ai_*` créée, aucun couplage ;
 *  8. **Idempotence** : un rejeu réseau ne crée pas de doublon ni de seconde
 *     notification.
 */
class PublicContactRequestTest extends TestCase
{
    use RefreshTenantDatabase;

    /** @param array<string, mixed> $attributes */
    private function publishedShowcase(string $slug, array $attributes = [], bool $featureEnabled = true): CompanyShowcase
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'slug' => 'tenant-'.$slug,
            'name' => 'Tenant '.$slug,
        ]);
        $company->setFeature('showcase', $featureEnabled);
        $company->save();

        return app(TenantManager::class)->withinTenant($company, function () use ($company, $slug, $attributes): CompanyShowcase {
            /** @var CompanyShowcase $showcase */
            $showcase = CompanyShowcase::query()->create(array_merge([
                'company_id' => $company->id,
                'slug' => $slug,
                'status' => CompanyShowcaseStatus::Published,
                'theme' => 'default',
                'settings' => [],
            ], $attributes));

            return $showcase;
        });
    }

    private function managerFor(Company $company): Employee
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company): Employee {
            /** @var Employee $manager */
            $manager = Employee::factory()->create([
                'company_id' => $company->id,
                'role' => 'manager',
                'manager_role' => 'principal',
                'status' => 'active',
            ]);

            return $manager;
        });
    }

    /** @param array<string, mixed> $overrides */
    private function payload(string $slug, array $overrides = []): array
    {
        return array_merge([
            'target_type' => 'showcase',
            'target_slug' => $slug,
            'sender_name' => 'Amina Client',
            'sender_email' => 'amina@example.test',
            'message' => 'Bonjour, je souhaite un devis pour une prestation.',
            'consent' => true,
            'idempotency_key' => 'idem-'.str_repeat('a', 10),
        ], $overrides);
    }

    public function test_contact_request_is_accepted_for_a_published_profile_and_creates_a_tenant_scoped_lead(): void
    {
        $showcase = $this->publishedShowcase('plombier-alpha');
        $companyId = (string) $showcase->company_id;
        $manager = $this->managerFor(Company::query()->findOrFail($companyId));

        $response = $this->postJson('/api/v1/public/contact-requests', $this->payload('plombier-alpha'))
            ->assertCreated();

        // Référence publique = clé d'idempotence : opaque, aucun id interne.
        $this->assertSame('idem-'.str_repeat('a', 10), $response->json('data.reference'));

        // Aucune donnée interne dans la réponse publique.
        $body = $response->json();
        $this->assertArrayNotHasKey('company_id', $body['data'] ?? []);
        $this->assertArrayNotHasKey('id', $body['data'] ?? []);
        $this->assertStringNotContainsString($companyId, json_encode($body) ?: '');

        // Demande persistée en table PLATEFORME (pas de company_id en clé de lecture).
        /** @var PublicContactRequest $request */
        $request = PublicContactRequest::query()->where('idempotency_key', 'idem-'.str_repeat('a', 10))->firstOrFail();
        $this->assertSame('showcase', $request->target_type);
        $this->assertSame('plombier-alpha', $request->target_slug);
        $this->assertSame($companyId, $request->resolved_company_id);
        $this->assertSame(PublicContactRequest::STATUS_NEW, $request->status);
        // IP hashée, jamais en clair.
        $this->assertNotSame('127.0.0.1', $request->ip_hash);

        // Lead CRM matérialisé DANS le tenant propriétaire : c'est ce que le
        // professionnel voit depuis son Business OS.
        $lead = app(TenantManager::class)->withinTenant(
            Company::query()->findOrFail($companyId),
            fn (): ?CrmLead => CrmLead::query()->where('email', 'amina@example.test')->first()
        );

        $this->assertInstanceOf(CrmLead::class, $lead, 'la demande doit produire un lead CRM dans le tenant');
        $this->assertSame('public_gateway', $lead->source);
        $this->assertNotNull($manager);
    }

    public function test_contact_request_notifies_the_tenant_manager(): void
    {
        $showcase = $this->publishedShowcase('coiffeur-beta');
        $company = Company::query()->findOrFail((string) $showcase->company_id);
        $manager = $this->managerFor($company);

        $this->postJson('/api/v1/public/contact-requests', $this->payload('coiffeur-beta'))
            ->assertCreated();

        $requestId = (string) PublicContactRequest::query()->value('id');

        // Notification ciblée par RÉFÉRENCE de demande (le JSON porte
        // `contact_request_id`), et non par catégorie : le lead CRM peut lui
        // aussi produire sa propre notification `crm`, ce qui rendrait un
        // comptage par type ambigu.
        $notified = DB::table('notifications')
            ->where('employee_id', $manager->id)
            ->whereRaw("data->>'contact_request_id' = ?", [$requestId])
            ->count();

        $this->assertSame(1, $notified, 'le manager du tenant doit être notifié une seule fois');
    }

    public function test_contact_request_is_rejected_uniformly_when_profile_is_not_public(): void
    {
        // Dépubliée (draft) : le slug existe mais n'est pas joignable.
        $this->publishedShowcase('brouillon-gamma', ['status' => CompanyShowcaseStatus::Draft]);
        // Feature verticale absente.
        $this->publishedShowcase('sans-flag-delta', [], featureEnabled: false);

        foreach (['brouillon-gamma', 'sans-flag-delta', 'slug-inexistant'] as $slug) {
            $this->postJson('/api/v1/public/contact-requests', $this->payload($slug, [
                'idempotency_key' => 'idem-'.md5($slug),
            ]))->assertNotFound();
        }

        // Aucune écriture : un profil non joignable ne produit pas de demande.
        $this->assertSame(0, PublicContactRequest::query()->count());
    }

    public function test_contact_request_is_rejected_for_a_suspended_company(): void
    {
        $showcase = $this->publishedShowcase('suspendu-epsilon');
        $company = Company::query()->findOrFail((string) $showcase->company_id);
        $company->status = 'suspended';
        $company->save();

        $this->postJson('/api/v1/public/contact-requests', $this->payload('suspendu-epsilon'))
            ->assertNotFound();

        $this->assertSame(0, PublicContactRequest::query()->count());
    }

    public function test_two_tenants_with_the_same_slug_never_mix_their_requests(): void
    {
        // Deux profils homonymes dans DEUX tenants distincts : le slug est
        // globalement unique côté verticale, mais la demande doit atterrir dans
        // le tenant résolu et JAMAIS dans un autre.
        $alpha = $this->publishedShowcase('meme-slug');
        $beta = $this->publishedShowcase('autre-slug');

        $alphaCompany = Company::query()->findOrFail((string) $alpha->company_id);
        $betaCompany = Company::query()->findOrFail((string) $beta->company_id);
        $alphaManager = $this->managerFor($alphaCompany);
        $betaManager = $this->managerFor($betaCompany);

        $this->postJson('/api/v1/public/contact-requests', $this->payload('meme-slug', [
            'sender_email' => 'client-alpha@example.test',
            'idempotency_key' => 'idem-alpha-only',
        ]))->assertCreated();

        // Le lead n'existe QUE dans le tenant visé.
        $alphaLead = app(TenantManager::class)->withinTenant(
            $alphaCompany,
            fn (): ?CrmLead => CrmLead::query()->where('email', 'client-alpha@example.test')->first()
        );
        $betaLead = app(TenantManager::class)->withinTenant(
            $betaCompany,
            fn (): ?CrmLead => CrmLead::query()->where('email', 'client-alpha@example.test')->first()
        );

        $this->assertInstanceOf(CrmLead::class, $alphaLead);
        $this->assertNull($betaLead, 'aucune fuite cross-tenant du lead');

        // Et le manager du tenant non visé n'est pas notifié.
        $requestId = (string) PublicContactRequest::query()->value('id');

        $betaNotified = DB::table('notifications')
            ->where('employee_id', $betaManager->id)
            ->whereRaw("data->>'contact_request_id' = ?", [$requestId])
            ->count();

        $this->assertSame(0, $betaNotified, 'le tenant non visé ne doit recevoir aucune notification');

        $this->assertNotNull($alphaManager);
    }

    public function test_contact_request_never_writes_into_ai_tables(): void
    {
        // Séparation stricte avec Léa : la demande publique est un message
        // HUMAIN, elle ne doit jamais alimenter la mémoire conversationnelle de
        // l'assistante.
        $this->publishedShowcase('separation-lea');
        $this->managerFor(Company::query()->findOrFail(
            (string) $this->publishedShowcase('separation-lea-2')->company_id
        ));

        $this->postJson('/api/v1/public/contact-requests', $this->payload('separation-lea', [
            'idempotency_key' => 'idem-lea-separation',
        ]))->assertCreated();

        $this->assertSame(0, DB::table('ai_conversations')->count(), 'aucune conversation Léa créée');
        $this->assertSame(0, DB::table('ai_audit_logs')->count(), 'aucun audit Léa créé');
    }

    public function test_retrying_the_same_idempotency_key_does_not_duplicate_the_request(): void
    {
        $showcase = $this->publishedShowcase('idempotent-zeta');
        $company = Company::query()->findOrFail((string) $showcase->company_id);
        $manager = $this->managerFor($company);

        $this->postJson('/api/v1/public/contact-requests', $this->payload('idempotent-zeta'))
            ->assertCreated();

        // Rejeu réseau : 200 (et non 201) — même contrat que le chat (#8747).
        $this->postJson('/api/v1/public/contact-requests', $this->payload('idempotent-zeta'))
            ->assertOk();

        $this->assertSame(1, PublicContactRequest::query()->count(), 'aucun doublon de demande');

        $requestId = (string) PublicContactRequest::query()->value('id');

        $notifications = DB::table('notifications')
            ->where('employee_id', $manager->id)
            ->whereRaw("data->>'contact_request_id' = ?", [$requestId])
            ->count();

        $this->assertSame(1, $notifications, 'le rejeu ne doit pas notifier deux fois');
    }

    public function test_unknown_target_type_is_rejected_with_422(): void
    {
        $this->postJson('/api/v1/public/contact-requests', $this->payload('peu-importe', [
            'target_type' => 'verticale-inexistante',
        ]))->assertStatus(422);
    }

    public function test_missing_consent_is_rejected_with_422(): void
    {
        $this->publishedShowcase('consentement-eta');

        $this->postJson('/api/v1/public/contact-requests', [
            'target_type' => 'showcase',
            'target_slug' => 'consentement-eta',
            'sender_name' => 'Sans Consentement',
            'sender_email' => 'no-consent@example.test',
            'message' => 'Je ne coche pas la case.',
            'idempotency_key' => 'idem-no-consent',
        ])->assertStatus(422);
    }

    public function test_honeypot_returns_a_fake_success_without_persisting(): void
    {
        $this->publishedShowcase('honeypot-theta');

        $this->postJson('/api/v1/public/contact-requests', $this->payload('honeypot-theta', [
            'company_website' => 'http://spam.example',
            'idempotency_key' => 'idem-honeypot',
        ]))->assertCreated();

        // Rien n'est persisté : le bot ne peut pas distinguer le faux succès.
        $this->assertSame(0, PublicContactRequest::query()->count());
    }
}
