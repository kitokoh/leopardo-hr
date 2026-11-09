<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Core\Tenant\Domain\Enums\PlatformRole;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Recruitment\Domain\Enums\JobSourceStatus;
use App\Modules\Recruitment\Domain\Models\ExternalJobOffer;
use App\Modules\Recruitment\Domain\Models\JobSource;
use App\Modules\Recruitment\Infrastructure\JobBoards\JobBoardClientRegistry;
use App\Modules\Recruitment\Infrastructure\JobBoards\JobBoardOffer;
use App\Modules\Recruitment\Infrastructure\JobBoards\JobBoardUnavailableException;
use App\Modules\Recruitment\Infrastructure\Services\JobSourceLifecycle;
use App\Modules\Recruitment\Infrastructure\Services\SyncExternalJobOffers;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * BC-10 (#9100) — portail public d'offres : publication plateforme et
 * connecteurs d'agrégateurs.
 *
 * Ce test verrouille les garanties qui ne se voient pas à l'œil nu :
 *  - aucun secret ne sort d'une réponse API ;
 *  - un test de connecteur ne prétend JAMAIS que l'accès fournisseur est
 *    vérifié ;
 *  - la synchronisation est idempotente ;
 *  - une offre disparue du flux est clôturée, jamais supprimée ;
 *  - les permissions plateforme sont réellement appliquées.
 */
final class PlatformRecruitmentPortalTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    // ─── Connecteurs : le secret ne sort jamais ──────────────────────────────

    public function test_configuring_a_source_never_returns_the_secret(): void
    {
        Sanctum::actingAs($this->platformAccount('admin@leopardo.test', PlatformRole::Admin), ['*'], 'super_admin_api');

        $response = $this->putJson('/api/v1/platform/recruitment/sources/adzuna', [
            'credentials' => ['app_id' => 'app-123', 'app_key' => 'super-secret-key'],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.provider', 'adzuna');
        $response->assertJsonPath('data.has_credentials', true);
        // L'état reste honnête : configurer ne connecte pas.
        $response->assertJsonPath('data.status', 'not_connected');

        // Le corps de la réponse ne doit contenir AUCUNE valeur de secret.
        $this->assertStringNotContainsString('super-secret-key', (string) $response->getContent());
        $this->assertStringNotContainsString('app-123', (string) $response->getContent());
    }

    public function test_credentials_are_encrypted_at_rest(): void
    {
        Sanctum::actingAs($this->platformAccount('admin@leopardo.test', PlatformRole::Admin), ['*'], 'super_admin_api');

        $this->putJson('/api/v1/platform/recruitment/sources/jooble', [
            'credentials' => ['api_key' => 'plain-text-key'],
        ])->assertOk();

        // La colonne brute ne doit PAS contenir la valeur en clair : c'est la
        // garantie qu'un dump de base ne livre pas les identifiants.
        $raw = (string) \Illuminate\Support\Facades\DB::table('public.job_sources')
            ->where('provider', 'jooble')
            ->value('credentials_encrypted');

        $this->assertNotSame('', $raw);
        $this->assertStringNotContainsString('plain-text-key', $raw);
    }

    public function test_an_unknown_credential_key_is_rejected(): void
    {
        Sanctum::actingAs($this->platformAccount('admin@leopardo.test', PlatformRole::Admin), ['*'], 'super_admin_api');

        // `apiKey` au lieu de `api_key` : persisté silencieusement, il serait
        // ignoré à la synchro et le connecteur « aurait l'air » configuré.
        $this->putJson('/api/v1/platform/recruitment/sources/jooble', [
            'credentials' => ['apiKey' => 'x'],
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'INVALID_CREDENTIALS');
    }

    public function test_testing_a_source_never_claims_provider_access_is_verified(): void
    {
        Sanctum::actingAs($this->platformAccount('admin@leopardo.test', PlatformRole::Admin), ['*'], 'super_admin_api');

        $this->putJson('/api/v1/platform/recruitment/sources/adzuna', [
            'credentials' => ['app_id' => 'a', 'app_key' => 'b'],
        ])->assertOk();

        $response = $this->postJson('/api/v1/platform/recruitment/sources/adzuna/test');

        $response->assertOk();
        // Sans appel réseau, on ne peut PAS prouver l'accès : le drapeau doit
        // rester false et l'état `not_connected`.
        $response->assertJsonPath('result.provider_access_verified', false);
        $response->assertJsonPath('data.status', 'not_connected');
    }

    public function test_revoking_a_source_erases_the_stored_secret(): void
    {
        Sanctum::actingAs($this->platformAccount('admin@leopardo.test', PlatformRole::Admin), ['*'], 'super_admin_api');

        $this->putJson('/api/v1/platform/recruitment/sources/jooble', [
            'credentials' => ['api_key' => 'to-be-erased'],
        ])->assertOk();

        $this->deleteJson('/api/v1/platform/recruitment/sources/jooble')
            ->assertOk()
            ->assertJsonPath('data.has_credentials', false);

        // Conserver un secret après révocation serait une fuite dormante.
        $this->assertNull(
            \Illuminate\Support\Facades\DB::table('public.job_sources')
                ->where('provider', 'jooble')
                ->value('credentials_encrypted')
        );
    }

    public function test_an_unsupported_provider_is_refused(): void
    {
        Sanctum::actingAs($this->platformAccount('admin@leopardo.test', PlatformRole::Admin), ['*'], 'super_admin_api');

        $this->putJson('/api/v1/platform/recruitment/sources/monster', [
            'credentials' => ['api_key' => 'x'],
        ])->assertNotFound();
    }

    // ─── Permissions plateforme ──────────────────────────────────────────────

    public function test_a_read_only_role_cannot_configure_a_source(): void
    {
        // `marketing` a `recruitment.view` mais PAS `recruitment.manage` :
        // publier au nom d'un client engage la marque publiquement.
        Sanctum::actingAs($this->platformAccount('marketing@leopardo.test', PlatformRole::Marketing), ['*'], 'super_admin_api');

        $this->getJson('/api/v1/platform/recruitment/sources')->assertOk();

        $this->putJson('/api/v1/platform/recruitment/sources/jooble', [
            'credentials' => ['api_key' => 'x'],
        ])->assertForbidden();
    }

    public function test_a_tenant_token_cannot_reach_platform_recruitment_routes(): void
    {
        // Le guard `super_admin_api` n'est pas celui d'un employé : un token
        // tenant ne doit pas pouvoir publier au nom d'un autre client.
        $this->getJson('/api/v1/platform/recruitment/sources')->assertUnauthorized();
    }

    // ─── Publication plateforme ──────────────────────────────────────────────

    public function test_platform_admin_publishes_a_job_for_a_tenant(): void
    {
        $admin = $this->platformAccount('admin@leopardo.test', PlatformRole::Admin);
        Sanctum::actingAs($admin, ['*'], 'super_admin_api');

        $company = Company::factory()->create(['slug' => 'acme-corp']);

        $response = $this->postJson('/api/v1/platform/recruitment/jobs', [
            'company_id' => $company->id,
            'title' => 'Ingénieur Plateforme',
            'description' => 'Rejoignez l’équipe.',
            'location' => 'Alger',
            'publish' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.title', 'Ingénieur Plateforme');
        $response->assertJsonPath('data.status', 'published');

        // L'offre doit être traçable comme publiée par la plateforme, sinon le
        // client ne peut pas savoir qui a publié sur sa page carrières.
        $this->assertDatabaseHas('job_postings', [
            'company_id' => $company->id,
            'title' => 'Ingénieur Plateforme',
            'status' => 'published',
            'published_by_platform' => true,
            'platform_created_by' => $admin->id,
        ]);
    }

    public function test_platform_published_job_appears_on_the_public_portal(): void
    {
        $admin = $this->platformAccount('admin@leopardo.test', PlatformRole::Admin);
        Sanctum::actingAs($admin, ['*'], 'super_admin_api');

        $company = Company::factory()->create(['slug' => 'acme-corp']);

        $this->postJson('/api/v1/platform/recruitment/jobs', [
            'company_id' => $company->id,
            'title' => 'Data Engineer',
            'publish' => true,
        ])->assertCreated();

        // Le portail public est anonyme : on repart sans authentification.
        $this->app['auth']->forgetGuards();

        $this->getJson("/api/v1/public/careers/{$company->slug}")
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Data Engineer');
    }

    // ─── Synchronisation ─────────────────────────────────────────────────────

    public function test_sync_is_idempotent(): void
    {
        $source = $this->connectedSource('custom_feed');

        $this->bindFakeClient([
            $this->offer('ext-1', 'Backend Engineer'),
            $this->offer('ext-2', 'Frontend Engineer'),
        ]);

        $service = app(SyncExternalJobOffers::class);

        $first = $service->execute($source);
        $second = $service->execute($source->refresh());

        $this->assertSame(2, $first['imported']);
        $this->assertSame(2, $second['imported']);

        // Deux exécutions = même état : c'est la clé métier unique qui le
        // garantit, pas une vérification applicative.
        $this->assertSame(2, ExternalJobOffer::query()->where('source_id', $source->id)->count());
    }

    public function test_sync_closes_vanished_offers_instead_of_deleting_them(): void
    {
        $source = $this->connectedSource('custom_feed');

        $this->bindFakeClient([
            $this->offer('ext-1', 'Backend Engineer'),
            $this->offer('ext-2', 'Frontend Engineer'),
        ]);
        app(SyncExternalJobOffers::class)->execute($source);

        // Le flux ne contient plus que ext-1.
        $this->bindFakeClient([$this->offer('ext-1', 'Backend Engineer')]);
        $result = app(SyncExternalJobOffers::class)->execute($source->refresh());

        $this->assertSame(1, $result['closed']);

        // L'offre disparue est CLÔTURÉE, jamais supprimée : les candidatures
        // déjà reçues doivent survivre à la disparition de l'annonce.
        $this->assertDatabaseHas('public.external_job_offers', [
            'source_id' => $source->id,
            'external_id' => 'ext-2',
            'status' => 'closed',
        ]);
    }

    public function test_sync_marks_the_source_connected_only_after_a_real_success(): void
    {
        $source = $this->connectedSource('custom_feed');
        $source->update(['status' => JobSourceStatus::NotConnected]);

        $this->bindFakeClient([$this->offer('ext-1', 'Backend Engineer')]);

        app(SyncExternalJobOffers::class)->execute($source->refresh());

        $this->assertSame(JobSourceStatus::Connected, $source->refresh()->status);
        $this->assertNotNull($source->last_synced_at);
    }

    public function test_a_non_retryable_failure_puts_the_source_in_error(): void
    {
        $source = $this->connectedSource('custom_feed');

        $this->bindFailingClient(new JobBoardUnavailableException('identifiants refusés', retryable: false));

        $result = app(SyncExternalJobOffers::class)->execute($source->refresh());

        $this->assertSame('error', $result['status']);
        $this->assertSame(JobSourceStatus::Error, $source->refresh()->status);
        $this->assertNotNull($source->last_error);
    }

    public function test_a_retryable_failure_keeps_the_source_connected(): void
    {
        $source = $this->connectedSource('custom_feed');

        $this->bindFailingClient(new JobBoardUnavailableException('timeout réseau', retryable: true));

        app(SyncExternalJobOffers::class)->execute($source->refresh());

        // Un hoquet réseau ne doit pas faire clignoter l'écran super admin.
        $this->assertSame(JobSourceStatus::Connected, $source->refresh()->status);
    }

    public function test_imported_offers_appear_on_the_public_portal(): void
    {
        $company = Company::factory()->create(['slug' => 'acme-corp', 'country' => 'DZ']);
        $source = $this->connectedSource('custom_feed');

        $this->bindFakeClient([$this->offer('ext-1', 'Agrégée Backend', country: 'DZ')]);
        app(SyncExternalJobOffers::class)->execute($source);

        $this->getJson("/api/v1/public/careers/{$company->slug}")
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Agrégée Backend')
            ->assertJsonPath('data.0.origin', 'external');
    }

    public function test_the_public_portal_never_exposes_the_raw_payload(): void
    {
        $company = Company::factory()->create(['slug' => 'acme-corp', 'country' => 'DZ']);
        $source = $this->connectedSource('custom_feed');

        $this->bindFakeClient([$this->offer('ext-1', 'Agrégée Backend', country: 'DZ')]);
        app(SyncExternalJobOffers::class)->execute($source);

        $response = $this->getJson("/api/v1/public/careers/{$company->slug}");

        // `raw_payload` peut contenir des champs internes du fournisseur :
        // il ne doit JAMAIS atteindre un visiteur anonyme.
        $response->assertOk();
        $this->assertStringNotContainsString('raw_payload', (string) $response->getContent());
        $this->assertStringNotContainsString('credentials', (string) $response->getContent());
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function platformAccount(string $email, PlatformRole $role): SuperAdmin
    {
        $account = new SuperAdmin([
            'name' => 'Platform '.$role->value,
            'email' => $email,
        ]);
        $account->forceFill([
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
            'platform_role' => $role->value,
        ])->save();

        return $account;
    }

    private function connectedSource(string $provider): JobSource
    {
        $lifecycle = app(JobSourceLifecycle::class);

        $source = $lifecycle->configure(
            $provider,
            ['feed_url' => 'https://jobs.example.com/feed.xml'],
            null,
            null,
        );

        $source->update(['status' => JobSourceStatus::Connected]);

        return $source->refresh();
    }

    /**
     * @param  list<JobBoardOffer>  $offers
     */
    private function bindFakeClient(array $offers): void
    {
        $this->app->bind(JobBoardClientRegistry::class, fn (): JobBoardClientRegistry => new class($offers) extends JobBoardClientRegistry
        {
            /** @param list<JobBoardOffer> $offers */
            public function __construct(private readonly array $offers)
            {
                parent::__construct(
                    app(\App\Modules\Recruitment\Infrastructure\JobBoards\CustomFeedJobBoardClient::class),
                    app(\App\Modules\Recruitment\Infrastructure\JobBoards\AdzunaJobBoardClient::class),
                    app(\App\Modules\Recruitment\Infrastructure\JobBoards\JoobleJobBoardClient::class),
                    app(\App\Modules\Recruitment\Infrastructure\JobBoards\IndeedJobBoardClient::class),
                    app(\App\Modules\Recruitment\Infrastructure\JobBoards\LinkedInJobBoardClient::class),
                    app(\App\Modules\Recruitment\Infrastructure\JobBoards\GlassdoorJobBoardClient::class),
                );
            }

            public function for(string $provider): \App\Modules\Recruitment\Infrastructure\JobBoards\JobBoardClient
            {
                return new class($this->offers) implements \App\Modules\Recruitment\Infrastructure\JobBoards\JobBoardClient
                {
                    /** @param list<JobBoardOffer> $offers */
                    public function __construct(private readonly array $offers) {}

                    public function fetch(array $credentials, array $config): array
                    {
                        return $this->offers;
                    }
                };
            }
        });
    }

    private function bindFailingClient(JobBoardUnavailableException $exception): void
    {
        $this->app->bind(JobBoardClientRegistry::class, fn (): JobBoardClientRegistry => new class($exception) extends JobBoardClientRegistry
        {
            public function __construct(private readonly JobBoardUnavailableException $exception)
            {
                parent::__construct(
                    app(\App\Modules\Recruitment\Infrastructure\JobBoards\CustomFeedJobBoardClient::class),
                    app(\App\Modules\Recruitment\Infrastructure\JobBoards\AdzunaJobBoardClient::class),
                    app(\App\Modules\Recruitment\Infrastructure\JobBoards\JoobleJobBoardClient::class),
                    app(\App\Modules\Recruitment\Infrastructure\JobBoards\IndeedJobBoardClient::class),
                    app(\App\Modules\Recruitment\Infrastructure\JobBoards\LinkedInJobBoardClient::class),
                    app(\App\Modules\Recruitment\Infrastructure\JobBoards\GlassdoorJobBoardClient::class),
                );
            }

            public function for(string $provider): \App\Modules\Recruitment\Infrastructure\JobBoards\JobBoardClient
            {
                return new class($this->exception) implements \App\Modules\Recruitment\Infrastructure\JobBoards\JobBoardClient
                {
                    public function __construct(private readonly JobBoardUnavailableException $exception) {}

                    public function fetch(array $credentials, array $config): array
                    {
                        throw $this->exception;
                    }
                };
            }
        });
    }

    private function offer(string $externalId, string $title, string $country = 'DZ'): JobBoardOffer
    {
        return new JobBoardOffer(
            externalId: $externalId,
            title: $title,
            companyName: 'Partenaire SA',
            applyUrl: 'https://jobs.example.com/'.$externalId,
            description: 'Description de l’offre.',
            location: 'Alger',
            country: $country,
            publishedAt: now(),
        );
    }
}
