<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Core\Tenant\Domain\Enums\PlatformRole;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Core\Tenant\TenantManager;
use App\Modules\Marketing\Domain\Models\MarketingAsset;
use App\Modules\Marketing\Domain\Models\MarketingProductionTask;
use App\Modules\Marketing\Domain\Models\MarketingPublication;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Marketing OS — surface Super Admin (issue #8528).
 *
 * Ces tests couvrent les garanties que le brief rend non négociables :
 *
 *  1. le mode de publication est CALCULÉ par le serveur — on ne peut pas
 *     présenter comme automatisée une publication qui exige une main humaine ;
 *  2. l'état « publiée » exige l'URL RÉELLE : ouvrir une page de réseau social
 *     ne publie rien ;
 *  3. une idée se transforme en tâche de production sans perdre la saisie, et
 *     l'opération est idempotente ;
 *  4. une ressource alimente PLUSIEURS publications, dont l'historique est
 *     conservé (aucun écrasement) ;
 *  5. l'isolation tenant tient : on ne rattache jamais la ressource d'une
 *     autre entreprise ;
 *  6. l'écriture exige `marketing_os.manage` — le rôle de lecture ne peut pas
 *     confirmer une mise en ligne ;
 *  7. les compteurs du tableau de bord proviennent de données réelles, et les
 *     statistiques non mesurées sont déclarées indisponibles.
 */
class MarketingOsPlatformTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Les binaires de la médiathèque partent sur le disk privé `local` :
        // on le remplace par un disk factice pour ne rien écrire sur le disque.
        Storage::fake('local');
        // Les assertions portent sur les libellés français du catalogue
        // `marketing.os.*` : on fige la locale via l'en-tête lu par le
        // middleware `SetLocale`, pour que le test ne dépende pas de
        // l'`APP_LOCALE` ambiante (en = défaut du dépôt).
        $this->withHeader('Accept-Language', 'fr');
    }

    private function company(?string $name = null): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create($name !== null ? ['name' => $name] : []);

        return $company;
    }

    private function actingAsPlatform(PlatformRole $role = PlatformRole::SuperAdmin): SuperAdmin
    {
        /** @var SuperAdmin $account */
        $account = new SuperAdmin([
            'name' => 'Platform '.$role->value,
            'email' => 'marketing-os-'.$role->value.'@leopardo.test',
        ]);
        $account->forceFill([
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
            'platform_role' => $role->value,
        ])->save();

        Sanctum::actingAs($account, ['*'], 'super_admin_api');

        return $account;
    }

    private function url(Company $company, string $path = ''): string
    {
        return "/api/v1/admin/companies/{$company->id}/marketing-os{$path}";
    }

    /** Gestionnaire de tenants (même convention que les tests multi-tenant). */
    private function tenants(): TenantManager
    {
        return app(TenantManager::class);
    }

    /**
     * Exécute une assertion DIRECTE en base dans le contexte du tenant.
     *
     * Les tables marketing vivent dans le schéma `shared_tenants` : une requête
     * Eloquent lancée hors contexte tenant ne les voit pas (d'où les erreurs
     * « relation … does not exist »). On passe donc par `withinTenant()`, qui
     * installe le `search_path` du tenant — exactement ce que fait le
     * contrôleur en production.
     *
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     */
    private function inTenant(Company $company, \Closure $callback): mixed
    {
        return $this->tenants()->withinTenant($company, $callback);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 1. Le mode est calculé, jamais déclaré
    // ─────────────────────────────────────────────────────────────────────

    public function test_requested_direct_mode_falls_back_to_assisted_without_a_connector(): void
    {
        $company = $this->company();
        $this->actingAsPlatform();

        // Aucun `social_accounts` actif pour cette entreprise : le client a
        // beau DEMANDER le mode direct, le serveur ne l'accorde pas.
        $response = $this->postJson($this->url($company, '/publications'), [
            'platform' => 'tiktok',
            'mode' => 'direct',
            'title' => 'Lancement Leopardo',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.mode', 'assisted')
            ->assertJsonPath('meta.direct_available', false);

        $this->assertStringContainsString(
            'assistée',
            (string) $response->json('meta.mode_explanation'),
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // 2. « Publiée » exige une URL réelle
    // ─────────────────────────────────────────────────────────────────────

    public function test_publication_cannot_be_marked_published_without_a_real_url(): void
    {
        $company = $this->company();
        $this->actingAsPlatform();

        $publicationId = $this->postJson($this->url($company, '/publications'), [
            'platform' => 'linkedin',
            'title' => 'Article produit',
        ])->assertCreated()->json('data.id');

        // Ouvrir la plateforme ne publie rien : sans URL réelle, c'est refusé.
        $this->patchJson($this->url($company, "/publications/{$publicationId}/status"), [
            'action' => 'confirm_published',
        ])->assertStatus(422);

        $this->inTenant($company, function () use ($publicationId): void {
            $this->assertDatabaseHas('marketing_publications', [
                'id' => $publicationId,
                'status' => 'idea',
                'published_url' => null,
            ]);
        });
    }

    public function test_confirmation_records_the_real_url_and_date(): void
    {
        $company = $this->company();
        $this->actingAsPlatform();

        $publicationId = $this->postJson($this->url($company, '/publications'), [
            'platform' => 'youtube',
            'title' => 'Publicité restaurant',
        ])->assertCreated()->json('data.id');

        $this->patchJson($this->url($company, "/publications/{$publicationId}/status"), [
            'action' => 'confirm_published',
            'published_url' => 'https://www.youtube.com/watch?v=abcd1234',
            'platform_account' => 'Leopardo Officiel',
        ])->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.published_url', 'https://www.youtube.com/watch?v=abcd1234')
            ->assertJsonPath('data.is_published', true);

        $this->inTenant($company, function () use ($publicationId): void {
            /** @var MarketingPublication $publication */
            $publication = MarketingPublication::query()->findOrFail($publicationId);
            $this->assertNotNull($publication->published_at, 'La date réelle de publication doit être enregistrée.');
            $this->assertCount(1, $publication->attempt_history ?? []);
            $this->assertSame('youtube', $publication->platform);
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // 3. Idée → tâche de production, sans perte et idempotent
    // ─────────────────────────────────────────────────────────────────────

    public function test_idea_is_promoted_to_a_production_task_without_losing_its_data(): void
    {
        $company = $this->company();
        $this->actingAsPlatform();

        $ideaId = $this->postJson($this->url($company, '/ideas'), [
            'title' => 'Publicité restaurant — spécialités locales',
            'objective' => 'acquisition',
            'format' => 'video_short',
            'locale' => 'fr',
            'sector' => 'restaurant',
            'product' => 'Leopardo Restaurant',
            'production_due_at' => now()->addDays(3)->toDateString(),
            'brief' => ['concept' => 'Ambiance du service du soir', 'script' => 'Scène 1 — entrée du client'],
        ])->assertCreated()->json('data.id');

        $first = $this->postJson($this->url($company, "/ideas/{$ideaId}/promote"))
            ->assertOk()
            ->assertJsonPath('meta.production_task_created', true)
            ->assertJsonPath('data.status', 'in_production')
            ->assertJsonPath('data.title', 'Publicité restaurant — spécialités locales')
            ->assertJsonPath('data.sector', 'restaurant')
            ->assertJsonPath('data.brief.concept', 'Ambiance du service du soir');

        $taskId = $first->json('data.production_task_id');
        $this->assertNotNull($taskId, 'La tâche de production doit exister.');
        // Le brief saisi n'est PAS perdu par le passage en production.
        $this->assertSame('Scène 1 — entrée du client', $first->json('data.brief.script'));

        // Idempotence : repromouvoir ne crée pas une seconde tâche.
        $this->postJson($this->url($company, "/ideas/{$ideaId}/promote"))
            ->assertOk()
            ->assertJsonPath('data.production_task_id', $taskId);

        $this->inTenant($company, function () use ($taskId): void {
            // La tâche de production vit dans le module Marketing : `tasks`
            // (Planning) exige un `created_by` employé, qu'un acteur plateforme
            // n'a pas (cf. MarketingProductionTask).
            /** @var MarketingProductionTask $task */
            $task = MarketingProductionTask::query()->findOrFail($taskId);
            $this->assertSame('todo', $task->status);
            $this->assertSame('marketing', $task->category);
            $this->assertStringContainsString('Production marketing : Publicité restaurant', $task->title);
            // Une seule tâche a été créée pour cette idée.
            $this->assertSame(
                1,
                MarketingProductionTask::query()->where('content_idea_id', $task->content_idea_id)->count(),
            );
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // 4. Une ressource, plusieurs publications, historique conservé
    // ─────────────────────────────────────────────────────────────────────

    public function test_a_resource_can_back_several_publications_and_keeps_history(): void
    {
        $company = $this->company();
        $this->actingAsPlatform();

        // Ressource TEXTE (script) : aucun fichier exigé.
        $assetId = $this->postJson($this->url($company, '/assets'), [
            'name' => 'Script publicité restaurant',
            'kind' => 'script',
            'description' => 'Texte du spot de 20 secondes',
            'sector' => 'restaurant',
            'tags' => ['publicité restaurant', 'lancement Leopardo'],
        ])->assertCreated()
            ->assertJsonPath('data.has_file', false)
            ->assertJsonPath('meta.text_only', true)
            ->json('data.id');

        // Binaire : la ressource versionnée est stockée hors base de données.
        $this->postJson($this->url($company, '/assets'), [
            'name' => 'Affiche lancement',
            'kind' => 'image',
            'sector' => 'restaurant',
        ], [])->assertStatus(422); // un type binaire sans fichier est refusé

        $this->call('POST', $this->url($company, '/assets'), [
            'name' => 'Affiche lancement',
            'kind' => 'image',
            'sector' => 'restaurant',
        ], [], ['file' => UploadedFile::fake()->image('affiche.jpg', 400, 400)], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.has_file', true)
            ->assertJsonPath('data.version', 1);

        // La même ressource alimente DEUX publications.
        foreach (['linkedin', 'facebook'] as $platform) {
            $this->postJson($this->url($company, '/publications'), [
                'platform' => $platform,
                'marketing_asset_id' => $assetId,
                'title' => 'Script publicité restaurant',
            ])->assertCreated();
        }

        $history = $this->getJson($this->url($company, "/assets/{$assetId}/publications"))
            ->assertOk()
            ->assertJsonPath('data.reusable', true);

        $this->assertCount(2, $history->json('data.publications'));
        $this->assertSame('Script publicité restaurant', $history->json('data.asset.name'));
        $this->assertEqualsCanonicalizing(['linkedin', 'facebook'], $history->json('data.platforms'));

        // Un binaire reste hors base : seule la référence de stockage est portée.
        $this->inTenant($company, function (): void {
            $binary = MarketingAsset::query()->where('name', 'Affiche lancement')->firstOrFail();
            $this->assertNotNull($binary->disk);
            $this->assertNotNull($binary->path);
            $this->assertNotNull($binary->size);
            $this->assertNotNull($binary->mime_type);
        });
    }

    public function test_asset_versions_never_overwrite_the_previous_one(): void
    {
        $company = $this->company();
        $this->actingAsPlatform();

        $this->postJson($this->url($company, '/assets'), [
            'name' => 'Affiche lancement',
            'kind' => 'script',
        ])->assertCreated()->assertJsonPath('data.version', 1);

        $this->postJson($this->url($company, '/assets'), [
            'name' => 'Affiche lancement',
            'kind' => 'script',
        ])->assertCreated()->assertJsonPath('data.version', 2);

        // Les deux versions coexistent : aucune n'écrase l'autre.
        $this->inTenant($company, function (): void {
            $versions = MarketingAsset::query()
                ->where('name', 'Affiche lancement')
                ->pluck('version')
                ->all();

            $this->assertCount(2, $versions);
            $this->assertEqualsCanonicalizing([1, 2], $versions);
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // 5. Isolation tenant
    // ─────────────────────────────────────────────────────────────────────

    public function test_a_resource_from_another_tenant_cannot_be_linked(): void
    {
        $companyA = $this->company('Entreprise A');
        $companyB = $this->company('Entreprise B');

        $this->actingAsPlatform();

        $foreignAssetId = $this->postJson($this->url($companyB, '/assets'), [
            'name' => 'Ressource de B',
            'kind' => 'script',
        ])->assertCreated()->json('data.id');

        // Depuis l'entreprise A, la ressource de B est introuvable → refus explicite.
        $this->postJson($this->url($companyA, '/publications'), [
            'platform' => 'linkedin',
            'marketing_asset_id' => $foreignAssetId,
        ])->assertStatus(422);

        // Et la lecture de A ne voit que les données de A.
        $this->getJson($this->url($companyA, '/assets'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 6. Permissions
    // ─────────────────────────────────────────────────────────────────────

    public function test_read_only_role_cannot_write_or_confirm_a_publication(): void
    {
        $company = $this->company();
        // Rôle plateforme « marketing » : lecture seule du Marketing OS.
        $this->actingAsPlatform(PlatformRole::Marketing);

        $this->getJson($this->url($company, '/ideas'))->assertOk();
        $this->getJson($this->url($company, '/overview'))->assertOk();

        $this->postJson($this->url($company, '/ideas'), [
            'title' => 'Tentative non autorisée',
        ])->assertStatus(403);

        $this->postJson($this->url($company, '/publications'), [
            'platform' => 'linkedin',
        ])->assertStatus(403);
    }

    public function test_tenant_guard_cannot_reach_the_platform_marketing_surface(): void
    {
        $company = $this->company();

        $employee = \App\Core\Auth\Domain\Models\Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        $this->getJson($this->url($company, '/overview'))->assertStatus(401);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 7. Tableau de bord : données réelles, statistiques honnêtes
    // ─────────────────────────────────────────────────────────────────────

    public function test_overview_reports_real_counts_and_declares_missing_stats(): void
    {
        $company = $this->company();
        $this->actingAsPlatform();

        $this->postJson($this->url($company, '/ideas'), [
            'title' => 'Idée non exploitée',
            'format' => 'image',
        ])->assertCreated();

        $publicationId = $this->postJson($this->url($company, '/publications'), [
            'platform' => 'facebook',
            'title' => 'Publication test',
        ])->assertCreated()->json('data.id');

        $this->patchJson($this->url($company, "/publications/{$publicationId}/status"), [
            'action' => 'confirm_published',
            'published_url' => 'https://www.facebook.com/leopardo/posts/123456',
        ])->assertOk();

        $overview = $this->getJson($this->url($company, '/overview'))->assertOk();

        $overview->assertJsonPath('data.content_pipeline.to_imagine', 1)
            ->assertJsonPath('data.content_pipeline.published', 0)
            // Aucune plateforme n'a remonté de statistiques : la source est
            // déclarée indisponible, aucun chiffre n'est extrapolé.
            ->assertJsonPath('data.publication_performance.published_total', 1)
            ->assertJsonPath('data.publication_performance.with_measured_stats', 0)
            ->assertJsonPath('data.publication_performance.stats_source', 'unavailable');

        $this->assertCount(1, $overview->json('data.recent_publications'));
        $this->assertSame(
            'https://www.facebook.com/leopardo/posts/123456',
            $overview->json('data.recent_publications.0.published_url'),
        );
        // L'idée non exploitée (aucune ressource produite) est bien signalée.
        $this->assertCount(1, $overview->json('data.unexploited_ideas'));
    }

    public function test_late_content_is_reported_from_real_dates(): void
    {
        $company = $this->company();
        $this->actingAsPlatform();

        $this->postJson($this->url($company, '/ideas'), [
            'title' => 'Contenu en retard',
            'format' => 'article',
            'publish_due_at' => now()->subDays(4)->toDateString(),
        ])->assertCreated();

        $overview = $this->getJson($this->url($company, '/overview'))->assertOk();

        $this->assertCount(1, $overview->json('data.late_content'));
        $this->assertSame('Contenu en retard', $overview->json('data.late_content.0.title'));
        $this->assertGreaterThanOrEqual(4, $overview->json('data.late_content.0.days_late'));
    }

    // ─────────────────────────────────────────────────────────────────────
    // Filtres de la médiathèque (section C : retrouver une ancienne vidéo)
    // ─────────────────────────────────────────────────────────────────────

    public function test_library_search_and_filters_find_an_old_asset(): void
    {
        $company = $this->company();
        $this->actingAsPlatform();

        $this->postJson($this->url($company, '/assets'), [
            'name' => 'Vidéo publicité restaurant 2025',
            'kind' => 'script',
            'sector' => 'restaurant',
            'locale' => 'fr',
            'tags' => ['publicité restaurant'],
        ])->assertCreated();

        $this->postJson($this->url($company, '/assets'), [
            'name' => 'Application taxi — tutoriel',
            'kind' => 'script',
            'sector' => 'transport',
            'locale' => 'fr',
            'tags' => ['application taxi'],
        ])->assertCreated();

        // Recherche « publicité restaurant » → retrouve l'ancienne création.
        $this->getJson($this->url($company, '/assets?search=publicité+restaurant'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Vidéo publicité restaurant 2025');

        // Filtre par verticale.
        $this->getJson($this->url($company, '/assets?sector=transport'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Application taxi — tutoriel');

        // Filtre par tag — sur la même requête, le tag est bien un filtre.
        $this->getJson($this->url($company, '/assets?tag=application%20taxi'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 8. Durcissement : idempotence, audit, preuve de publication
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Rejouer la MÊME confirmation (double clic, retry réseau) ne doit ni
     * échouer ni dupliquer l'historique : c'est un rejeu, pas un événement.
     */
    public function test_replaying_the_same_confirmation_is_a_no_op(): void
    {
        $company = $this->company();
        $this->actingAsPlatform();

        $publicationId = $this->postJson($this->url($company, '/publications'), [
            'platform' => 'linkedin',
            'title' => 'Lancement Leopardo',
        ])->assertCreated()->json('data.id');

        $payload = [
            'action' => 'confirm_published',
            'published_url' => 'https://www.linkedin.com/posts/leopardo-123',
            'platform_account' => 'Leopardo Officiel',
        ];

        $this->patchJson($this->url($company, "/publications/{$publicationId}/status"), $payload)
            ->assertOk()
            ->assertJsonPath('meta.idempotent', false)
            ->assertJsonPath('meta.changed', true);

        // Rejeu STRICTEMENT identique.
        $this->patchJson($this->url($company, "/publications/{$publicationId}/status"), $payload)
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('meta.idempotent', true)
            ->assertJsonPath('meta.changed', false);

        $this->inTenant($company, function () use ($publicationId): void {
            /** @var MarketingPublication $publication */
            $publication = MarketingPublication::query()->findOrFail($publicationId);

            // Une seule entrée d'historique : le rejeu n'a rien ajouté.
            $this->assertNotNull($publication->published_at);
            $this->assertCount(1, $publication->attempt_history ?? []);
        });
    }

    /**
     * Une transition RÉELLE laisse une trace dans la piste d'audit existante
     * (brief §J) ; un rejeu n'écrit rien.
     */
    public function test_a_real_status_change_is_audited_and_a_replay_is_not(): void
    {
        $company = $this->company();
        $this->actingAsPlatform();

        $publicationId = $this->postJson($this->url($company, '/publications'), [
            'platform' => 'youtube',
            'title' => 'Publicité restaurant',
        ])->assertCreated()->json('data.id');

        $payload = [
            'action' => 'confirm_published',
            'published_url' => 'https://www.youtube.com/watch?v=zzzz9999',
        ];

        $this->patchJson($this->url($company, "/publications/{$publicationId}/status"), $payload)->assertOk();
        $this->patchJson($this->url($company, "/publications/{$publicationId}/status"), $payload)->assertOk();

        $this->inTenant($company, function () use ($publicationId, $company): void {
            /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Core\Auth\Domain\Models\AuditLog> $entries */
            $entries = \App\Core\Auth\Domain\Models\AuditLog::query()
                ->where('module', 'marketing')
                ->where('action', 'marketing.publication.published')
                ->where('auditable_id', $publicationId)
                ->get();

            // Une transition réelle = une ligne d'audit, même après rejeu.
            $this->assertCount(1, $entries, 'Le rejeu ne doit pas dupliquer la piste d\'audit.');

            $entry = $entries->first();
            $this->assertNotNull($entry);
            $this->assertSame('idea', $entry->old_values['status']);
            $this->assertSame('published', $entry->new_values['status']);
            $this->assertSame((string) $company->id, (string) $entry->company_id);
        });
    }

    /**
     * `published` ne peut PAS être déclaré à la création : sans cette garde,
     * un simple POST contournait l'exigence d'URL réelle.
     */
    public function test_published_status_cannot_be_declared_at_creation(): void
    {
        $company = $this->company();
        $this->actingAsPlatform();

        $this->postJson($this->url($company, '/publications'), [
            'platform' => 'linkedin',
            'title' => 'Tentative de contournement',
            'status' => 'published',
        ])->assertStatus(422);

        $this->postJson($this->url($company, '/publications'), [
            'platform' => 'linkedin',
            'title' => 'Tentative de contournement',
            'status' => 'failed',
        ])->assertStatus(422);

        // Les statuts de travail restent, eux, acceptés.
        $this->postJson($this->url($company, '/publications'), [
            'platform' => 'linkedin',
            'title' => 'Préparation',
            'status' => 'in_preparation',
        ])->assertCreated()->assertJsonPath('data.status', 'in_preparation');

        $this->inTenant($company, function (): void {
            $this->assertSame(0, MarketingPublication::query()->where('status', 'published')->count());
        });
    }

    /**
     * « Peut-on réutiliser cette ressource ? » dépend de droits RÉELS, pas
     * d'une constante : des droits expirés bloquent, l'absence de droits non.
     */
    public function test_asset_reuse_reflects_real_rights_expiry(): void
    {
        $company = $this->company();
        $this->actingAsPlatform();

        // Les types BINAIRES exigent un fichier (`MarketingAssetKind::requiresFile()`),
        // sinon le controleur repond 422 : on televerse donc de vrais faux fichiers.
        $expiredId = $this->call('POST', $this->url($company, '/assets'), [
            'name' => 'Visuel sous licence expirée',
            // Kind TEXTE : ces deux tests portent sur l'expiration des droits et
            // sur l'échappement de la recherche, pas sur le dépôt de fichier.
            // Un kind binaire (`image`, `video`) est refusé sans fichier par la
            // garde `requiresFile()` du contrôleur — à raison : une ressource
            // « image » sans image serait une ressource fantôme. Utiliser un
            // kind texte préserve l'intention du test et le contrat.
            'kind' => 'copy',
            'rights' => 'Licence stock limitée',
            'rights_expires_at' => '2020-01-01',
        ], [], [], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data.id');

        $freeId = $this->call('POST', $this->url($company, '/assets'), [
            'name' => 'Création interne',
            'kind' => 'video',
        ], [], ['file' => UploadedFile::fake()->create('interne.mp4', 10, 'video/mp4')], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data.id');

        $this->getJson($this->url($company, "/assets/{$expiredId}/publications"))
            ->assertOk()
            ->assertJsonPath('data.reusable', false)
            ->assertJsonCount(1, 'data.reuse_blockers');

        // Pas de droits déclarés ≠ droits absents : on ne bloque pas sur une
        // information manquante, on la signale simplement.
        $this->getJson($this->url($company, "/assets/{$freeId}/publications"))
            ->assertOk()
            ->assertJsonPath('data.reusable', true)
            ->assertJsonPath('data.reuse_blockers', []);
    }

    /**
     * Le tableau de bord ne doit PAS étiqueter « plateforme » des chiffres
     * saisis à la main (brief §A/§H).
     */
    public function test_manual_stats_are_not_reported_as_platform_measurements(): void
    {
        $company = $this->company();
        $this->actingAsPlatform();

        $publicationId = $this->postJson($this->url($company, '/publications'), [
            'platform' => 'linkedin',
        ])->assertCreated()->json('data.id');

        // Un humain confirme la publication AVEC des chiffres saisis : la
        // provenance doit être `manual`, jamais `platform`.
        $this->patchJson($this->url($company, "/publications/{$publicationId}/status"), [
            'action' => 'confirm_published',
            'published_url' => 'https://www.linkedin.com/posts/leopardo-manual',
            'stats' => ['impressions' => 1200, 'clicks' => 40],
        ])->assertOk();

        $this->getJson($this->url($company, '/overview'))
            ->assertOk()
            ->assertJsonPath('data.publication_performance.stats_source', 'manual')
            ->assertJsonPath('data.publication_performance.measured_from_platform', 0)
            ->assertJsonPath('data.publication_performance.measured_manually', 1);
    }

    // ──────────────────────────────────────────────────────────────────────
    // 9. Robustesse des listes : recherche, filtres, pagination
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Un `%` ou un `_` saisi dans la recherche est un CARACTERE, pas un joker.
     *
     * Sans echappement, chercher « 100% » ramenait tout le catalogue (le `%`
     * etant interprete comme « n'importe quoi ») : l'utilisateur croyait avoir
     * trouve une correspondance exacte alors qu'il voyait la liste complete.
     */
    public function test_search_wildcards_are_escaped_and_do_not_match_everything(): void
    {
        $company = $this->company();
        $this->actingAsPlatform();

        // `kind: image` exige un fichier (garde du controleur) : sans binaire,
        // ces creations repondraient 422 et le test ne testerait plus l'echappement.
        $this->call('POST', $this->url($company, '/assets'), [
            'name' => 'Remise 100% restaurant',
            'kind' => 'image',
        ], [], ['file' => UploadedFile::fake()->image('remise.jpg', 100, 100)], ['Accept' => 'application/json'])
            ->assertCreated();

        $this->call('POST', $this->url($company, '/assets'), [
            'name' => 'Visuel sans rapport',
            'kind' => 'image',
        ], [], ['file' => UploadedFile::fake()->image('sans-rapport.jpg', 100, 100)], ['Accept' => 'application/json'])
            ->assertCreated();

        // « 100% » ne doit ramener QUE la ressource qui contient litteralement
        // cette chaine — pas les deux.
        $this->getJson($this->url($company, '/assets?search=100%25'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Remise 100% restaurant');

        // Un motif qui ne correspond a rien reste vide : le `%` seul ne doit
        // pas se comporter comme « tout ».
        //
        // Nuance que l'assertion initiale ratait : le jeu de donnees de ce test
        // contient LUI-MEME un `%` litteral (« Remise 100% restaurant »). Avec
        // l'echappement correct, chercher `%` ramene donc exactement cette
        // ressource — et non les deux. Attendre 0 confondait « le `%` est
        // echappe » avec « aucune donnee ne contient de `%` », et echouait sur
        // un comportement pourtant juste. L'assertion qui prouve reellement
        // l'echappement est celle du dessus (1 resultat sur 2 ressources) ;
        // celle-ci verifie qu'un motif sans correspondance ne ramene rien.
        $this->getJson($this->url($company, '/assets?search=%25'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Remise 100% restaurant');

        // Contre-preuve : un motif reellement absent ne ramene rien du tout.
        $this->getJson($this->url($company, '/assets?search=zzz-inexistant'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * Un filtre hors vocabulaire est REFUSE, pas silencieusement vide.
     *
     * `status=publié` (accent, ou faute de frappe) renvoyait 200 avec une liste
     * vide : l'interface affichait « aucun resultat » alors que la requete
     * etait simplement malformee. Le contrat est desormais explicite (422).
     */
    public function test_unknown_enum_filters_are_rejected_instead_of_returning_an_empty_list(): void
    {
        $company = $this->company();
        $this->actingAsPlatform();

        $this->getJson($this->url($company, '/publications?status=publié'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->getJson($this->url($company, '/publications?mode=automatique'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('mode');

        $this->getJson($this->url($company, '/assets?kind=film'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('kind');

        $this->getJson($this->url($company, '/ideas?format=reel'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('format');

        $this->getJson($this->url($company, '/campaigns?objective=vente'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('objective');

        // Un filtre VALIDE continue de fonctionner : on n'a pas casse la liste.
        $this->getJson($this->url($company, '/publications?status=idea'))
            ->assertOk();
    }

    /**
     * Les objectifs de visibilite sont PAGINES, pas tronques en silence.
     *
     * L'ancien `limit(100)` coupait la liste sans le dire : l'interface ne
     * pouvait ni afficher le total ni atteindre les objectifs suivants.
     */
    public function test_visibility_objectives_are_paginated_with_a_real_total(): void
    {
        $company = $this->company();
        $this->actingAsPlatform();

        foreach (range(1, 3) as $index) {
            $this->postJson($this->url($company, '/visibility-objectives'), [
                'channel' => 'seo',
                'keyword' => "mot-cle-{$index}",
            ])->assertCreated();
        }

        $this->getJson($this->url($company, '/visibility-objectives?per_page=2'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.per_page', 2);

        $this->getJson($this->url($company, '/visibility-objectives?per_page=2&page=2'))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /**
     * `attempts` et `attempt_history` racontent le MEME fait.
     *
     * Une publication reussie du premier coup affichait `attempts = 0` alors
     * que son historique contenait deja une entree : deux compteurs du meme
     * evenement qui se contredisaient a l'ecran.
     */
    public function test_a_successful_publication_counts_its_attempt(): void
    {
        $company = $this->company();
        $this->actingAsPlatform();

        $publicationId = $this->postJson($this->url($company, '/publications'), [
            'platform' => 'linkedin',
        ])->assertCreated()->json('data.id');

        $this->patchJson($this->url($company, "/publications/{$publicationId}/status"), [
            'action' => 'confirm_published',
            'published_url' => 'https://www.linkedin.com/posts/leopardo-attempts',
        ])->assertOk();

        $this->inTenant($company, function () use ($publicationId): void {
            /** @var MarketingPublication $publication */
            $publication = MarketingPublication::query()->findOrFail($publicationId);

            $this->assertSame(1, $publication->attempts);
            $this->assertCount(1, (array) $publication->attempt_history);
            $this->assertSame(
                $publication->attempts,
                count((array) $publication->attempt_history),
                'Le compteur de tentatives doit correspondre a l historique.',
            );
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // 10. #8675 — la médiathèque est LISIBLE (détail + téléchargement)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Le chaînon manquant : un fichier téléversé doit pouvoir être RÉCUPÉRÉ.
     *
     * Sans cette route, la médiathèque était en écriture seule — on pouvait
     * téléverser une vidéo et ne plus jamais la revoir. Le test vérifie le
     * contenu réellement servi (et pas seulement un code 200), parce qu'un
     * téléchargement qui renvoie un fichier vide est un faux positif.
     */
    public function test_a_media_file_can_be_downloaded_with_its_original_name(): void
    {
        $company = $this->company();
        $this->actingAsPlatform();

        $assetId = $this->call('POST', $this->url($company, '/assets'), [
            'name' => 'Affiche lancement',
            'kind' => 'image',
        ], [], ['file' => UploadedFile::fake()->image('affiche lancement.jpg', 400, 400)], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.has_file', true)
            // Le nom d'origine était calculé puis JETÉ : il est désormais persisté.
            ->assertJsonPath('data.original_name', 'affiche-lancement.jpg')
            ->assertJsonPath('data.download_name', 'affiche-lancement.jpg')
            ->json('data.id');

        $download = $this->get($this->url($company, "/assets/{$assetId}/download"));

        $download->assertOk();
        // Le nom proposé est le nom d'origine sanitisé, pas le nom de stockage
        // aléatoire de 32 caractères hexadécimaux.
        $this->assertStringContainsString(
            'affiche-lancement.jpg',
            (string) $download->headers->get('content-disposition'),
        );
    }

    /**
     * Une ressource d'une AUTRE entreprise n'est jamais servie.
     *
     * C'est le scénario de fuite inter-tenant le plus direct : le chemin
     * `/companies/{A}/…/assets/{idDeB}` ne doit pas exfiltrer le binaire de B.
     */
    public function test_a_media_file_from_another_tenant_is_not_downloadable(): void
    {
        $companyA = $this->company('Entreprise A');
        $companyB = $this->company('Entreprise B');
        $this->actingAsPlatform();

        $foreignId = $this->call('POST', $this->url($companyB, '/assets'), [
            'name' => 'Ressource de B',
            'kind' => 'image',
        ], [], ['file' => UploadedFile::fake()->image('secret-b.jpg', 100, 100)], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data.id');

        // Depuis la surface de A, l'identifiant de B est introuvable.
        $this->getJson($this->url($companyA, "/assets/{$foreignId}"))->assertNotFound();
        $this->get($this->url($companyA, "/assets/{$foreignId}/download"))->assertNotFound();

        // Et la surface de B continue de fonctionner (on n'a pas cassé l'accès légitime).
        $this->get($this->url($companyB, "/assets/{$foreignId}/download"))->assertOk();
    }

    /**
     * Le détail d'une ressource est accessible hors de la fenêtre de pagination.
     */
    public function test_a_single_asset_detail_is_reachable_outside_the_listing_page(): void
    {
        $company = $this->company();
        $this->actingAsPlatform();

        $assetId = $this->postJson($this->url($company, '/assets'), [
            'name' => 'Script lancement Leopardo',
            'kind' => 'script',
            'description' => 'Texte du spot',
            'tags' => ['lancement Leopardo'],
        ])->assertCreated()->json('data.id');

        $this->getJson($this->url($company, "/assets/{$assetId}"))
            ->assertOk()
            ->assertJsonPath('data.id', $assetId)
            ->assertJsonPath('data.name', 'Script lancement Leopardo')
            ->assertJsonPath('data.tags', ['lancement Leopardo'])
            // Une ressource texte n'a pas de binaire : l'API le dit, et le
            // téléchargement renvoie un 404 explicite plutôt qu'un fichier vide.
            ->assertJsonPath('meta.downloadable', false)
            ->assertJsonPath('meta.text_only', true);

        $this->getJson($this->url($company, "/assets/{$assetId}/download"))
            ->assertNotFound()
            ->assertJsonPath('message', __('marketing.os.asset_has_no_file', [], 'fr'));
    }
}
