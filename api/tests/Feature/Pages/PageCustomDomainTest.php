<?php

declare(strict_types=1);

namespace Tests\Feature\Pages;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\Site;
use App\Modules\Pages\Domain\CustomDomains\Contracts\CustomDomainProvider;
use App\Modules\Pages\Domain\CustomDomains\Dns\DnsResolver;
use App\Modules\Pages\Domain\CustomDomains\Dns\InMemoryDnsResolver;
use App\Modules\Pages\Domain\CustomDomains\DomainName;
use App\Modules\Pages\Domain\CustomDomains\Providers\InMemoryCustomDomainProvider;
use App\Modules\Pages\Domain\Enums\PageStatus;
use App\Modules\Pages\Domain\Enums\PageVerificationStatus;
use App\Modules\Pages\Domain\Exceptions\PageCustomDomainException;
use App\Modules\Pages\Domain\Models\Page;
use App\Modules\Pages\Domain\Models\PageCustomDomain;
use App\Modules\Pages\Domain\Services\PageCustomDomainService;
use App\Shared\Contracts\Entitlements\FeatureEntitlements;
use Illuminate\Support\Carbon;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #8726 — domaines personnalisés : normalisation, DNS, certificat, entitlement.
 *
 * Tout le réseau est remplacé par des doubles en mémoire : aucun test ne doit
 * interroger le DNS réel ni un fournisseur, sinon la CI devient intermittente.
 */
class PageCustomDomainTest extends TestCase
{
    use RefreshTenantDatabase;

    private Site $site;

    private Page $page;

    private InMemoryDnsResolver $dns;

    private InMemoryCustomDomainProvider $provider;

    private PageCustomDomainService $service;

    private string $secret;

    /** @var array<string, array{enabled?: bool, limit?: int|null}> */
    private array $entitlements = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->secret = 'test-secret';
        config()->set('pages.custom_domains.provider', 'memory');
        config()->set('pages.custom_domains.txt_secret', $this->secret);

        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);

        $this->site = Site::create([
            'company_id' => $company->id,
            'name' => 'Chez Léa',
            'code' => 'CL-01',
            'site_type' => 'store',
            'status' => Site::STATUS_ACTIVE,
        ]);

        $page = Page::create([
            'company_id' => $company->id,
            'site_id' => $this->site->id,
            'slug' => 'chez-lea',
            'status' => PageStatus::Published,
            'published_at' => Carbon::now(),
            'verification_status' => PageVerificationStatus::Verified,
            'verified_at' => Carbon::now(),
            // La contrainte `pages_verified_requires_proof` exige une méthode :
            // c'est voulu, une Page vérifiée sans preuve n'existe pas.
            'verification_method' => 'superadmin',
        ]);
        $this->page = $page;

        $this->dns = new InMemoryDnsResolver;
        $this->provider = new InMemoryCustomDomainProvider;

        // Entitlement piloté par le test : on ne dépend pas de la vraie matrice.
        $guard = \Mockery::mock(FeatureEntitlements::class);
        $guard->shouldReceive('isFeatureEnabled')
            ->andReturnUsing(fn (string $companyId, string $feature): bool => $this->entitlements[$feature]['enabled'] ?? true);
        $guard->shouldReceive('featureLimit')
            ->andReturnUsing(fn (string $companyId, string $feature): int => $this->entitlements[$feature]['limit'] ?? 1);

        /** @var FeatureEntitlements $entitlements */
        $entitlements = $guard;

        $this->app->instance(DnsResolver::class, $this->dns);
        $this->app->instance(CustomDomainProvider::class, $this->provider);

        $this->service = new PageCustomDomainService($this->dns, $this->provider, $entitlements, app(\App\Shared\Contracts\Persistence\TransactionRunner::class));
    }

    /** Pose les records DNS que le service attend pour un host donné. */
    private function proveDns(string $host): void
    {
        $this->dns->setTxt(
            DomainName::txtRecordName($host),
            DomainName::txtRecordValue($host, $this->secret),
        );
        $this->dns->setCname($host, DomainName::cnameTarget($host));
        $this->dns->setResolves($host, true);
    }

    // ══ Normalisation ══════════════════════════════════════════════════════

    public function test_host_is_lowercased_and_stripped_of_scheme_and_trailing_slash(): void
    {
        $this->assertSame('chez-lea.sn', DomainName::normalize('  HTTPS://Chez-Lea.SN/  '));
    }

    public function test_unicode_domain_is_converted_to_ascii_so_it_cannot_be_claimed_twice(): void
    {
        $this->assertSame('xn--caf-dma.sn', DomainName::normalize('café.sn'));
        // Les deux écritures produisent la MÊME clé en base : pas de doublon.
        $this->assertSame(
            DomainName::normalize('café.sn'),
            DomainName::normalize('XN--CAF-DMA.SN'),
        );
    }

    public function test_ip_address_is_rejected(): void
    {
        $this->assertNull(DomainName::normalize('93.184.216.34'));
    }

    public function test_single_label_is_rejected(): void
    {
        $this->assertNull(DomainName::normalize('localhost'));
    }

    public function test_path_is_rejected(): void
    {
        $this->assertNull(DomainName::normalize('chez-lea.sn/menu'));
    }

    public function test_label_starting_with_hyphen_is_rejected(): void
    {
        $inspection = DomainName::inspect('-invalid.sn');
        $this->assertFalse($inspection->isValid());
    }

    public function test_label_too_long_is_rejected(): void
    {
        $this->assertNull(DomainName::normalize(str_repeat('a', 64).'.sn'));
    }

    public function test_underscore_is_rejected(): void
    {
        $this->assertNull(DomainName::normalize('che_z-lea.sn'));
    }

    public function test_platform_suffix_cannot_be_delegated(): void
    {
        $inspection = DomainName::inspect('pages.leopardo.com');
        $this->assertFalse($inspection->isValid());
        $this->assertStringContainsString('leopardo.com', $inspection->errorSummary());
    }

    public function test_public_mail_provider_cannot_be_delegated(): void
    {
        // Une Page « vérifiée » sur gmail.com serait un vecteur d'hameçonnage.
        $this->assertNull(DomainName::normalize('gmail.com'));
    }

    public function test_apex_and_wildcard_are_flagged(): void
    {
        $apex = DomainName::inspect('chez-lea.sn');
        $this->assertTrue($apex->isApex);
        $this->assertFalse($apex->isWildcard);

        $wildcard = DomainName::inspect('*.chez-lea.sn');
        $this->assertTrue($wildcard->isWildcard);
        $this->assertFalse($wildcard->isApex);
    }

    public function test_www_subdomain_is_accepted_and_not_apex(): void
    {
        $inspection = DomainName::inspect('www.chez-lea.sn');
        $this->assertTrue($inspection->isValid());
        $this->assertFalse($inspection->isApex);
    }

    public function test_txt_and_cname_derivations_are_stable_and_host_specific(): void
    {
        $this->assertSame(
            DomainName::txtRecordValue('a.sn', $this->secret),
            DomainName::txtRecordValue('a.sn', $this->secret),
        );
        $this->assertNotSame(
            DomainName::txtRecordValue('a.sn', $this->secret),
            DomainName::txtRecordValue('b.sn', $this->secret),
        );
        $this->assertStringStartsWith('_leopardo-pages.', DomainName::txtRecordName('a.sn'));
    }

    // ══ Demande ════════════════════════════════════════════════════════════

    public function test_request_returns_dns_instructions_and_does_not_touch_the_provider(): void
    {
        $domain = $this->service->request($this->page, 'chez-lea.sn', 'owner@chez-lea.sn');

        $this->assertSame('chez-lea.sn', $domain->host);
        $this->assertSame(PageCustomDomain::STATUS_PENDING_DNS, $domain->status);

        /** @var array<string, array<string, string>> $instructions */
        $instructions = $domain->dns_instructions;
        $this->assertArrayHasKey('txt', $instructions);
        $this->assertArrayHasKey('cname', $instructions);
        $this->assertSame('_leopardo-pages.chez-lea.sn', $instructions['txt']['name']);

        // Rien n'est provisionné avant la preuve DNS.
        $this->assertSame([], $this->provider->log());
        $this->assertFalse($this->provider->isProvisioned('chez-lea.sn'));
    }

    public function test_request_refuses_an_invalid_domain(): void
    {
        $this->expectException(PageCustomDomainException::class);

        $this->service->request($this->page, 'not_a_domain', 'owner@chez-lea.sn');
    }

    public function test_request_refuses_a_domain_taken_by_another_tenant_without_leaking_ownership(): void
    {
        $otherCompany = Company::factory()->create(['country' => 'SN']);
        $otherSite = Site::create([
            'company_id' => $otherCompany->id,
            'name' => 'Autre',
            'code' => 'AU-01',
            'site_type' => 'store',
            'status' => Site::STATUS_ACTIVE,
        ]);
        $otherPage = Page::create([
            'company_id' => $otherCompany->id,
            'site_id' => $otherSite->id,
            'slug' => 'autre',
            'status' => PageStatus::Draft,
        ]);

        $this->service->request($otherPage, 'shared.sn', 'other@autre.sn');

        try {
            $this->service->request($this->page, 'shared.sn', 'owner@chez-lea.sn');
            $this->fail('Un host déjà pris doit être refusé.');
        } catch (PageCustomDomainException $e) {
            $this->assertSame('CUSTOM_DOMAIN_TAKEN', $e->errorCode());
            // Le message ne doit pas révéler l'identité du tenant propriétaire.
            $this->assertStringNotContainsString('autre', strtolower($e->getMessage()));
        }
    }

    public function test_request_refuses_when_entitlement_is_missing(): void
    {
        $this->entitlements['pages_custom_domain'] = ['enabled' => false];

        $this->expectException(PageCustomDomainException::class);

        $this->service->request($this->page, 'chez-lea.sn', 'owner@chez-lea.sn');
    }

    public function test_request_refuses_when_quota_is_reached(): void
    {
        $this->entitlements['pages_custom_domain'] = ['enabled' => true, 'limit' => 1];
        $this->service->request($this->page, 'chez-lea.sn', 'owner@chez-lea.sn');

        $this->expectException(PageCustomDomainException::class);

        $this->service->request($this->page, 'menu.chez-lea.sn', 'owner@chez-lea.sn');
    }

    public function test_request_is_allowed_when_first_domain_is_removed(): void
    {
        $this->entitlements['pages_custom_domain'] = ['enabled' => true, 'limit' => 1];
        $first = $this->service->request($this->page, 'chez-lea.sn', 'owner@chez-lea.sn');
        $this->service->remove($first, 'owner@chez-lea.sn');

        $second = $this->service->request($this->page, 'menu.chez-lea.sn', 'owner@chez-lea.sn');

        $this->assertSame('menu.chez-lea.sn', $second->host);
    }

    // ══ Vérification DNS ═══════════════════════════════════════════════════

    public function test_missing_txt_keeps_the_domain_pending_and_serves_nothing(): void
    {
        $domain = $this->service->request($this->page, 'chez-lea.sn', 'owner@chez-lea.sn');
        $domain = $this->service->verify($domain);

        $this->assertSame(PageCustomDomain::STATUS_PENDING_DNS, $domain->status);
        $this->assertSame(1, $domain->check_attempts);
        $this->assertFalse($domain->canServe());
        $this->assertNull($this->service->resolveHost('chez-lea.sn'));
    }

    public function test_wrong_txt_value_is_refused(): void
    {
        $domain = $this->service->request($this->page, 'chez-lea.sn', 'owner@chez-lea.sn');
        $this->dns->setTxt(DomainName::txtRecordName('chez-lea.sn'), 'leopardo-verify=forged');

        $domain = $this->service->verify($domain);

        $this->assertSame(PageCustomDomain::STATUS_PENDING_DNS, $domain->status);
        $this->assertStringContainsString('inattendue', (string) $domain->last_error);
    }

    public function test_txt_alone_without_pointing_to_the_renderer_is_not_enough(): void
    {
        $domain = $this->service->request($this->page, 'chez-lea.sn', 'owner@chez-lea.sn');
        $this->dns->setTxt(
            DomainName::txtRecordName('chez-lea.sn'),
            DomainName::txtRecordValue('chez-lea.sn', $this->secret),
        );
        // TXT OK mais le host ne résout pas : le trafic n'arrive pas chez nous.

        $domain = $this->service->verify($domain);

        $this->assertSame(PageCustomDomain::STATUS_PENDING_DNS, $domain->status);
        $this->assertStringContainsString('ne résout pas', (string) $domain->last_error);
        $this->assertSame([], $this->provider->log());
    }

    public function test_verification_abandons_after_max_attempts(): void
    {
        $domain = $this->service->request($this->page, 'chez-lea.sn', 'owner@chez-lea.sn');

        for ($i = 0; $i < PageCustomDomain::MAX_CHECK_ATTEMPTS; $i++) {
            $domain = $this->service->verify($domain);
        }

        $this->assertSame(PageCustomDomain::STATUS_FAILED, $domain->status);
        $this->assertSame(PageCustomDomain::MAX_CHECK_ATTEMPTS, $domain->check_attempts);
        $this->assertStringContainsString('abandonnée', (string) $domain->last_error);
    }

    public function test_full_flow_reaches_active_after_dns_proof(): void
    {
        $domain = $this->service->request($this->page, 'chez-lea.sn', 'owner@chez-lea.sn');
        $this->proveDns('chez-lea.sn');

        $domain = $this->service->verify($domain);

        // Premier appel certificat : `pending`. Ce n'est pas un bug, c'est le
        // comportement réel d'un fournisseur — et c'est ce qu'on veut tester.
        $this->assertSame(PageCustomDomain::STATUS_PENDING_CERTIFICATE, $domain->status);
        $this->assertFalse($domain->canServe());

        $domain = $this->service->verify($domain, Carbon::now()->addMinute());

        $this->assertSame(PageCustomDomain::STATUS_ACTIVE, $domain->status);
        $this->assertTrue($domain->canServe());
        $this->assertNotNull($domain->verified_at);
        $this->assertNotNull($domain->certificate_expires_at);

        $page = $this->service->resolveHost('chez-lea.sn');
        $this->assertNotNull($page);
        $this->assertSame($this->page->id, $page->id);

        // Le provider a été appelé une seule fois en provision (idempotence).
        $provisions = array_filter($this->provider->log(), fn (string $e): bool => str_starts_with($e, 'provision:'));
        $this->assertCount(1, $provisions);
    }

    public function test_verify_is_idempotent_on_an_active_domain(): void
    {
        $domain = $this->service->request($this->page, 'chez-lea.sn', 'owner@chez-lea.sn');
        $this->proveDns('chez-lea.sn');
        $domain = $this->service->verify($domain);
        $domain = $this->service->verify($domain, Carbon::now()->addMinute());

        $this->assertSame(PageCustomDomain::STATUS_ACTIVE, $domain->status);
        $this->provider->resetLog();

        // Un troisième passage ne re-provisionne pas : c'est la propriété qui
        // rend le job de fond rejouable sans risque.
        $domain = $this->service->verify($domain, Carbon::now()->addMinutes(2));
        $this->assertSame([], $this->provider->log());
        $this->assertSame(PageCustomDomain::STATUS_ACTIVE, $domain->status);
    }

    // ══ Certificat : expiration et renouvellement ═════════════════════════

    public function test_expired_certificate_stops_serving_immediately(): void
    {
        $domain = $this->service->request($this->page, 'chez-lea.sn', 'owner@chez-lea.sn');
        $this->proveDns('chez-lea.sn');
        $domain = $this->service->verify($domain);
        $domain = $this->service->verify($domain, Carbon::now()->addMinute());

        $this->assertTrue($domain->canServe());

        // Le statut est toujours `active`, mais la date est dépassée : c'est
        // exactement le cas d'un webhook perdu. On ne sert PAS.
        $domain->certificate_expires_at = Carbon::now()->subMinute();
        $domain->save();

        $this->assertFalse($domain->fresh()?->canServe() ?? true);
        $this->assertNull($this->service->resolveHost('chez-lea.sn'));
    }

    public function test_refresh_marks_expired_and_requests_renewal(): void
    {
        $domain = $this->service->request($this->page, 'chez-lea.sn', 'owner@chez-lea.sn');
        $this->proveDns('chez-lea.sn');
        $domain = $this->service->verify($domain);
        $domain = $this->service->verify($domain, Carbon::now()->addMinute());

        $expiredAt = Carbon::now()->subHour();
        $domain->certificate_expires_at = $expiredAt;
        $domain->save();

        // Avant rafraîchissement : le statut est encore `active` mais la date est
        // dépassée — exactement le cas d'un webhook perdu. On ne sert PAS.
        $this->assertFalse($domain->fresh()?->canServe() ?? true);

        $refreshed = $this->service->refreshCertificate($domain->fresh());

        // Le rafraîchissement redemande un certificat ; l'adaptateur de test en
        // émet un nouveau, donc le domaine resservira — mais avec une échéance
        // repoussée. C'est l'échéance repoussée qui prouve le renouvellement.
        $this->assertNotNull($refreshed->certificate_expires_at);
        $this->assertTrue(
            $refreshed->certificate_expires_at->greaterThan($expiredAt),
            'Le certificat expiré doit être remplacé par un nouveau.',
        );
    }

    public function test_certificate_is_renewed_inside_the_window(): void
    {
        $domain = $this->service->request($this->page, 'chez-lea.sn', 'owner@chez-lea.sn');
        $this->proveDns('chez-lea.sn');
        $domain = $this->service->verify($domain);
        $domain = $this->service->verify($domain, Carbon::now()->addMinute());

        // Échéance dans 3 jours : dans la fenêtre de renouvellement de 14 jours.
        $domain->certificate_expires_at = Carbon::now()->addDays(3);
        $domain->save();

        $this->assertTrue($domain->needsCertificateRenewal());

        $this->provider->resetLog();
        $refreshed = $this->service->refreshCertificate($domain->fresh() ?? $domain);

        $renewals = array_filter($this->provider->log(), fn (string $e): bool => str_starts_with($e, 'certificate:'));
        $this->assertCount(1, $renewals, 'Le renouvellement doit être demandé.');
        $this->assertSame(PageCustomDomain::STATUS_ACTIVE, $refreshed->status);
    }

    public function test_no_renewal_outside_the_window(): void
    {
        $domain = $this->service->request($this->page, 'chez-lea.sn', 'owner@chez-lea.sn');
        $this->proveDns('chez-lea.sn');
        $domain = $this->service->verify($domain);
        $domain = $this->service->verify($domain, Carbon::now()->addMinute());

        $domain->certificate_expires_at = Carbon::now()->addDays(60);
        $domain->save();

        $this->assertFalse($domain->needsCertificateRenewal());

        $this->provider->resetLog();
        $this->service->refreshCertificate($domain->fresh() ?? $domain);

        $this->assertSame([], $this->provider->log());
    }

    // ══ Suppression ═══════════════════════════════════════════════════════

    public function test_removal_stops_serving_before_calling_the_provider(): void
    {
        $domain = $this->service->request($this->page, 'chez-lea.sn', 'owner@chez-lea.sn');
        $this->proveDns('chez-lea.sn');
        $domain = $this->service->verify($domain);
        $domain = $this->service->verify($domain, Carbon::now()->addMinute());

        $this->provider->resetLog();
        $removed = $this->service->remove($domain, 'owner@chez-lea.sn');

        $this->assertSame(PageCustomDomain::STATUS_REMOVED, $removed->status);
        $this->assertNotNull($removed->removed_at);
        $this->assertFalse($removed->canServe());
        $this->assertNull($this->service->resolveHost('chez-lea.sn'));

        $this->assertContains('deprovision:chez-lea.sn', $this->provider->log());
    }

    public function test_removal_is_idempotent(): void
    {
        $domain = $this->service->request($this->page, 'chez-lea.sn', 'owner@chez-lea.sn');
        $this->service->remove($domain, 'owner@chez-lea.sn');
        $again = $this->service->remove($domain->fresh() ?? $domain, 'owner@chez-lea.sn');

        $this->assertSame(PageCustomDomain::STATUS_REMOVED, $again->status);
    }

    public function test_verify_refuses_a_removed_domain(): void
    {
        $domain = $this->service->request($this->page, 'chez-lea.sn', 'owner@chez-lea.sn');
        $this->service->remove($domain, 'owner@chez-lea.sn');

        $this->expectException(PageCustomDomainException::class);

        $this->service->verify($domain->fresh() ?? $domain);
    }

    public function test_provider_failure_does_not_undo_the_local_removal(): void
    {
        $domain = $this->service->request($this->page, 'chez-lea.sn', 'owner@chez-lea.sn');

        $failingProvider = new class implements CustomDomainProvider
        {
            public function name(): string
            {
                return 'failing';
            }

            public function provision(string $host): \App\Modules\Pages\Domain\CustomDomains\Contracts\CustomDomainProviderResult
            {
                return \App\Modules\Pages\Domain\CustomDomains\Contracts\CustomDomainProviderResult::failed('down');
            }

            public function requestCertificate(string $host): \App\Modules\Pages\Domain\CustomDomains\Contracts\CustomDomainProviderResult
            {
                return \App\Modules\Pages\Domain\CustomDomains\Contracts\CustomDomainProviderResult::failed('down');
            }

            public function deprovision(string $host): \App\Modules\Pages\Domain\CustomDomains\Contracts\CustomDomainProviderResult
            {
                return \App\Modules\Pages\Domain\CustomDomains\Contracts\CustomDomainProviderResult::failed('timeout');
            }
        };

        $service = new PageCustomDomainService($this->dns, $failingProvider, app(FeatureEntitlements::class), app(\App\Shared\Contracts\Persistence\TransactionRunner::class));
        $removed = $service->remove($domain, 'owner@chez-lea.sn');

        $this->assertSame(PageCustomDomain::STATUS_REMOVED, $removed->status);
        $this->assertStringContainsString('échec', (string) $removed->last_error);
        $this->assertNull($this->service->resolveHost('chez-lea.sn'));
    }

    // ══ Routage public : fail-closed ═══════════════════════════════════════

    public function test_unknown_host_serves_nothing(): void
    {
        $this->assertNull($this->service->resolveHost('unknown.sn'));
    }

    public function test_invalid_host_serves_nothing(): void
    {
        $this->assertNull($this->service->resolveHost('not a host'));
    }

    public function test_domain_of_an_unpublished_page_serves_nothing(): void
    {
        $domain = $this->service->request($this->page, 'chez-lea.sn', 'owner@chez-lea.sn');
        $this->proveDns('chez-lea.sn');
        $domain = $this->service->verify($domain);
        $domain = $this->service->verify($domain, Carbon::now()->addMinute());

        $this->assertNotNull($this->service->resolveHost('chez-lea.sn'));

        // Une Page dépubliée ne sert plus, même avec un domaine `active` : le
        // domaine suit la Page, pas l'inverse.
        $page = $this->page->fresh();
        $this->assertNotNull($page);
        // `unpublish()` plutôt qu'une affectation directe : le schéma interdit
        // une Page brouillon avec `published_at` renseigné
        // (`pages_draft_has_no_published_date`). Passer par le domaine garantit
        // qu'on teste le comportement réel et pas un état impossible en base.
        $page->unpublish();
        $page->save();

        $this->assertNull($this->service->resolveHost('chez-lea.sn'));
    }

    public function test_host_is_matched_case_insensitively(): void
    {
        $domain = $this->service->request($this->page, 'chez-lea.sn', 'owner@chez-lea.sn');
        $this->proveDns('chez-lea.sn');
        $domain = $this->service->verify($domain);
        $this->service->verify($domain, Carbon::now()->addMinute());

        $this->assertNotNull($this->service->resolveHost('CHEZ-LEA.SN'));
    }

    // ══ Audit ══════════════════════════════════════════════════════════════

    public function test_every_step_is_audited(): void
    {
        $domain = $this->service->request($this->page, 'chez-lea.sn', 'owner@chez-lea.sn');
        $this->service->verify($domain);
        $this->proveDns('chez-lea.sn');
        $domain = $this->service->verify($domain->fresh() ?? $domain);
        $this->service->remove($domain->fresh() ?? $domain, 'owner@chez-lea.sn');

        $actions = \App\Core\Auth\Domain\Models\AuditLog::query()
            ->where('module', 'pages')
            ->pluck('action')
            ->all();

        $this->assertContains('page.custom_domain.requested', $actions);
        $this->assertContains('page.custom_domain.dns_pending', $actions);
        $this->assertContains('page.custom_domain.verified', $actions);
        $this->assertContains('page.custom_domain.removed', $actions);
    }

    public function test_audit_never_contains_the_txt_secret(): void
    {
        $domain = $this->service->request($this->page, 'chez-lea.sn', 'owner@chez-lea.sn');
        $this->service->verify($domain);

        $serialized = \App\Core\Auth\Domain\Models\AuditLog::query()->get()->toJson();

        $this->assertStringNotContainsString($this->secret, $serialized);
    }
}
