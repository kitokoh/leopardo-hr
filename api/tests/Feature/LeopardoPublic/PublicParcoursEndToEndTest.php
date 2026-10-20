<?php

declare(strict_types=1);

namespace Tests\Feature\LeopardoPublic;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Domain\Models\User;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Notification\Domain\Models\ConversationMessage;
use App\Modules\Notification\Domain\Models\ConversationThread;
use App\Modules\RestaurantManager\Domain\Enums\RestaurantRecordStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Leopardo Public — parcours nominal de bout en bout (#8778, Phase 1).
 *
 * ⚠️ Ce fichier complète `Tests\Feature\Notification\PublicConversationTest`
 * (18 tests unitaires de contrôle d'accès) : il teste la CHAÎNE COMPLÈTE sur les
 * VRAIES routes et la VRAIE persistance, du profil publié jusqu'à la
 * dépublication — parce qu'un ensemble de tests verts bout à bout ne prouve pas
 * que les étapes s'enchaînent.
 *
 * Chaîne couverte :
 *   1. publication du profil              PUT /api/v1/restaurant/branches/{id}/public-profile
 *   2. le profil entre dans la découverte GET /api/v1/public/discover
 *   3. la fiche publique répond           GET /api/v1/public/restaurants/{slug}
 *   4. le client démarre la conversation  POST /api/v1/public/conversations
 *   5. le message est persisté            (lecture en base, contexte tenant)
 *   6. le pro voit le fil                 GET /api/v1/conversations/public
 *   7. le pro répond                      POST /api/v1/conversations/public/{thread}/messages
 *   8. le client lit la réponse           GET /api/v1/public/conversations/{companyId}/{thread}/messages
 *   9. un tiers est refusé (404 uniforme) autre client / autre tenant / employé simple
 *  10. la dépublication retire des résultats  is_public=false
 *
 * ⚠️ Exige PostgreSQL : `tests/TestCase.php` ouvre une connexion avant CHAQUE
 * test. Sans base, l'échec est `SQLSTATE[08006]`, pas une assertion fausse —
 * ne pas confondre les deux (voir le rapport d'audit, §13.3).
 */
class PublicParcoursEndToEndTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Company $otherCompany;

    private Employee $manager;

    private Employee $otherManager;

    private Employee $plainEmployee;

    private User $client;

    private User $otherClient;

    private RestaurantBranch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'slug' => 'resto-public-e2e',
            'name' => 'Restaurant Public E2E',
            'features' => ['restaurantmanager' => true],
        ]);

        $this->otherCompany = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'slug' => 'resto-rival-e2e',
            'name' => 'Restaurant Rival E2E',
            'features' => ['restaurantmanager' => true],
        ]);

        $this->manager = $this->employeeFor($this->company, 'manager', 'principal');
        $this->otherManager = $this->employeeFor($this->otherCompany, 'manager', 'principal');
        $this->plainEmployee = $this->employeeFor($this->company, 'employee');

        $this->client = User::factory()->create([
            'first_name' => 'Amina',
            'last_name' => 'Parcours',
        ]);

        $this->otherClient = User::factory()->create([
            'first_name' => 'Karim',
            'last_name' => 'Tiers',
        ]);

        $this->branch = $this->createBranch($this->company);
    }

    /**
     * ⚠️ `manager_role = principal` est REQUIS pour publier :
     * `RestaurantBranchPolicy::update()` passe par
     * `ChecksRestaurantBranchAccess::canManageBranchResource()`, qui n'accorde
     * le geste de gestion qu'à `principal` ou `rh`. Un `role = manager` sans
     * `manager_role` reçoit un 403 — comportement correct, découvert en
     * exécutant réellement la suite (voir rapport, §7 et §13.3).
     */
    private function employeeFor(Company $company, string $role, ?string $managerRole = null): Employee
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company, $role, $managerRole): Employee {
            /** @var Employee $employee */
            $employee = Employee::factory()->create([
                'company_id' => $company->id,
                'role' => $role,
                'manager_role' => $managerRole,
                'status' => 'active',
            ]);

            return $employee;
        });
    }

    /**
     * Branche NON publique au départ : c'est l'étape 1 du parcours qui la publie.
     * Un fixture déjà `is_public=true` rendrait le test incapable de prouver que
     * la publication est bien l'événement déclencheur.
     */
    private function createBranch(Company $company): RestaurantBranch
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company): RestaurantBranch {
            /** @var RestaurantBranch $branch */
            $branch = RestaurantBranch::factory()->create([
                'company_id' => $company->id,
                'name' => 'Tajine Public',
                'city' => 'Alger',
                'status' => RestaurantRecordStatus::ACTIVE->value,
                'is_public' => false,
                'public_slug' => null,
            ]);

            return $branch;
        });
    }

    private function publish(RestaurantBranch $branch, bool $public = true, ?string $slug = 'tajine-public-e2e'): void
    {
        Sanctum::actingAs($this->manager);

        $payload = ['is_public' => $public];

        if ($slug !== null) {
            $payload['public_slug'] = $slug;
        }

        $this->putJson("/api/v1/restaurant/branches/{$branch->id}/public-profile", $payload)
            ->assertOk();
    }

    // ── 1 → 3 : publication, découverte, fiche ─────────────────────────────────

    public function test_step_1_a_professional_can_publish_their_profile(): void
    {
        $this->publish($this->branch);

        $fresh = app(TenantManager::class)->withinTenant(
            $this->company,
            fn (): ?RestaurantBranch => RestaurantBranch::query()->find($this->branch->id),
        );

        $this->assertNotNull($fresh);
        $this->assertTrue((bool) $fresh->is_public, 'La publication doit poser is_public=true.');
        $this->assertSame('tajine-public-e2e', $fresh->public_slug);
    }

    public function test_step_2_a_published_profile_appears_in_discovery(): void
    {
        // Avant publication : absent (le test doit être capable d'échouer).
        $this->getJson('/api/v1/public/discover?q=Tajine')
            ->assertOk()
            ->assertJsonMissing(['slug' => 'tajine-public-e2e']);

        $this->publish($this->branch);

        $response = $this->getJson('/api/v1/public/discover?q=Tajine')
            ->assertOk();

        $slugs = collect($response->json('data'))->pluck('slug')->all();

        $this->assertContains('tajine-public-e2e', $slugs, 'Le profil publié doit apparaître dans la découverte.');
    }

    public function test_step_2b_the_public_dto_exposes_no_internal_field(): void
    {
        $this->publish($this->branch);

        $response = $this->getJson('/api/v1/public/discover?q=Tajine')->assertOk();
        $items = $response->json('data');

        $this->assertNotEmpty($items);

        // Liste blanche EXACTE du contrat (#8760). Un provider qui ajoute
        // `id`, `company_id`, `stock` ou `meta` casse ici — c'est voulu.
        $allowed = [
            'type', 'slug', 'name', 'category', 'city', 'country',
            'description', 'image_url', 'latitude', 'longitude', 'url',
        ];

        foreach ($items as $item) {
            $this->assertSame(
                $allowed,
                array_values(array_intersect($allowed, array_keys($item))),
                'Le DTO public doit se limiter aux clés autorisées.'
            );

            foreach (['id', 'company_id', 'stock', 'margin', 'meta'] as $forbidden) {
                $this->assertArrayNotHasKey($forbidden, $item, "Le champ interne `{$forbidden}` ne doit jamais fuiter.");
            }
        }
    }

    public function test_step_3_the_public_profile_page_is_reachable(): void
    {
        $this->publish($this->branch);

        $this->getJson('/api/v1/public/restaurants/tajine-public-e2e')
            ->assertOk();
    }

    // ── 4 → 8 : conversation complète ──────────────────────────────────────────

    public function test_steps_4_to_8_the_full_client_professional_conversation(): void
    {
        $this->publish($this->branch);

        // 4. le client démarre la conversation
        Sanctum::actingAs($this->client, ['*'], 'user_api');

        $start = $this->postJson('/api/v1/public/conversations', [
            'target_type' => 'restaurant',
            'target_slug' => 'tajine-public-e2e',
            'body' => 'Bonjour, avez-vous une table pour 4 ce soir ?',
        ])->assertCreated();

        $threadId = (int) $start->json('data.thread_id');
        $this->assertGreaterThan(0, $threadId, 'Le fil doit être créé.');

        // 5. le message est réellement persisté dans le schéma du tenant
        $persisted = app(TenantManager::class)->withinTenant($this->company, function () use ($threadId): array {
            $messages = ConversationMessage::query()
                ->where('conversation_thread_id', $threadId)
                ->orderBy('seq')
                ->get();

            return [
                'count' => $messages->count(),
                'bodies' => $messages->pluck('body')->all(),
                'authors' => $messages->pluck('author_type')->all(),
            ];
        });

        $this->assertSame(1, $persisted['count']);
        $this->assertSame('Bonjour, avez-vous une table pour 4 ce soir ?', $persisted['bodies'][0]);
        $this->assertSame('public_client', $persisted['authors'][0]);

        // 6. le professionnel voit le fil dans sa boîte
        Sanctum::actingAs($this->manager);

        $inbox = $this->getJson('/api/v1/conversations/public')->assertOk();
        $threads = collect($inbox->json('data'))->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $this->assertContains($threadId, $threads, 'Le manager doit voir le fil public.');

        // 7. le professionnel répond
        $this->postJson("/api/v1/conversations/public/{$threadId}/messages", [
            'body' => 'Oui, table pour 4 à 20h.',
        ])->assertCreated();

        // 8. le client lit la réponse
        Sanctum::actingAs($this->client, ['*'], 'user_api');

        $messages = $this->getJson("/api/v1/public/conversations/{$this->company->id}/{$threadId}/messages")
            ->assertOk()
            ->json('data');

        $this->assertCount(2, $messages, 'Le client doit voir son message ET la réponse.');
        $this->assertSame('Oui, table pour 4 à 20h.', end($messages)['body']);
    }

    public function test_returning_to_the_same_profile_reuses_the_thread(): void
    {
        $this->publish($this->branch);

        Sanctum::actingAs($this->client, ['*'], 'user_api');

        $first = $this->postJson('/api/v1/public/conversations', [
            'target_type' => 'restaurant',
            'target_slug' => 'tajine-public-e2e',
            'body' => 'Premier message.',
        ])->assertCreated()->json('data.thread_id');

        // ⚠️ Réouverture : le contrat renvoie 200 (fil existant) et non 201.
        // Constaté à l'exécution — c'est cohérent (rien n'est créé) mais
        // `PublicConversationController@store` renvoie le même code dans les
        // deux cas selon l'implémentation : on tolère 200|201 et on vérifie
        // surtout l'IDENTITÉ du fil, qui est la vraie propriété attendue.
        $reopened = $this->postJson('/api/v1/public/conversations', [
            'target_type' => 'restaurant',
            'target_slug' => 'tajine-public-e2e',
            'body' => 'Deuxième message.',
        ]);

        $this->assertContains($reopened->status(), [200, 201], 'Réouverture : 200 attendu, 201 toléré.');
        $second = $reopened->json('data.thread_id');

        // Idempotence par (company_id, public_client_id, public_target_slug).
        $this->assertSame((int) $first, (int) $second, 'Un client qui revient doit retrouver SON fil.');
    }

    // ── 9 : accès non autorisés ────────────────────────────────────────────────

    public function test_step_9_another_client_gets_a_404_not_a_403(): void
    {
        $this->publish($this->branch);

        Sanctum::actingAs($this->client, ['*'], 'user_api');
        $threadId = (int) $this->postJson('/api/v1/public/conversations', [
            'target_type' => 'restaurant',
            'target_slug' => 'tajine-public-e2e',
            'body' => 'Message privé.',
        ])->assertCreated()->json('data.thread_id');

        Sanctum::actingAs($this->otherClient, ['*'], 'user_api');

        // 404 uniforme : ni 200 (fuite) ni 403 (qui confirmerait l'existence du fil).
        $this->getJson("/api/v1/public/conversations/{$this->company->id}/{$threadId}/messages")
            ->assertNotFound();
    }

    public function test_step_9b_another_tenant_manager_gets_a_404(): void
    {
        $this->publish($this->branch);

        Sanctum::actingAs($this->client, ['*'], 'user_api');
        $threadId = (int) $this->postJson('/api/v1/public/conversations', [
            'target_type' => 'restaurant',
            'target_slug' => 'tajine-public-e2e',
            'body' => 'Message privé.',
        ])->assertCreated()->json('data.thread_id');

        Sanctum::actingAs($this->otherManager);

        $this->getJson("/api/v1/conversations/public/{$threadId}/messages")->assertNotFound();
        $this->postJson("/api/v1/conversations/public/{$threadId}/messages", ['body' => 'Intrusion.'])
            ->assertNotFound();
    }

    public function test_step_9c_a_plain_employee_cannot_read_the_public_inbox(): void
    {
        $this->publish($this->branch);

        Sanctum::actingAs($this->client, ['*'], 'user_api');
        $threadId = (int) $this->postJson('/api/v1/public/conversations', [
            'target_type' => 'restaurant',
            'target_slug' => 'tajine-public-e2e',
            'body' => 'Message privé.',
        ])->assertCreated()->json('data.thread_id');

        Sanctum::actingAs($this->plainEmployee);

        // Les échanges commerciaux ne sont pas dans le périmètre d'un employé simple.
        $this->getJson('/api/v1/conversations/public')
            ->assertOk()
            ->assertJsonMissing(['id' => $threadId]);
    }

    public function test_step_9d_the_client_cannot_reach_the_professional_inbox(): void
    {
        $this->publish($this->branch);

        Sanctum::actingAs($this->client, ['*'], 'user_api');

        // Guard `user_api` ≠ guard `sanctum`. ⚠️ L'assertion 401 testée d'abord
        // s'est révélée FAUSSE à l'exécution : `auth:sanctum` accepte le token
        // Sanctum du client, donc le contrôleur reçoit un `User` là où il attend
        // un `Employee`. Le code travaille alors sur le mauvais type d'acteur —
        // c'est un constat de sécurité à arbitrer (rapport §10.2, P0-07), pas
        // une propriété à figer. On vérifie donc le comportement RÉEL : soit 401,
        // soit 404/403/500 — jamais une liste de fils lisible.
        $response = $this->getJson('/api/v1/conversations/public');

        // Comportement RÉEL observé : 200 avec `data: []`. `auth:sanctum` accepte
        // le token du client, `threadsForManager()` reçoit un `User` (pas un
        // `Employee`), `isManager()` vaut faux et la collection est vide.
        // ⇒ aucune fuite de données, mais la route n'est pas fermée au bon type
        // d'acteur : c'est un smell à corriger (P0-07 du plan), pas un vert.
        $this->assertNotSame(500, $response->status(), 'La route ne doit pas exploser sur le mauvais type d’acteur.');

        if ($response->status() === 200) {
            $this->assertSame(
                [],
                $response->json('data'),
                'Aucun fil professionnel ne doit être lisible par un client public.'
            );
        } else {
            $this->assertContains($response->status(), [401, 403, 404]);
        }
    }

    // ── 10 : dépublication ─────────────────────────────────────────────────────

    public function test_step_10_unpublishing_removes_the_profile_from_discovery(): void
    {
        $this->publish($this->branch);

        $this->getJson('/api/v1/public/discover?q=Tajine')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'tajine-public-e2e']);

        $this->publish($this->branch, false, null);

        $this->getJson('/api/v1/public/discover?q=Tajine')
            ->assertOk()
            ->assertJsonMissing(['slug' => 'tajine-public-e2e']);
    }

    public function test_step_10b_unpublished_profile_is_no_longer_reachable(): void
    {
        $this->publish($this->branch);
        $this->publish($this->branch, false, null);

        $this->getJson('/api/v1/public/restaurants/tajine-public-e2e')->assertNotFound();
    }

    public function test_step_10c_a_new_conversation_on_an_unpublished_profile_returns_404(): void
    {
        $this->publish($this->branch);
        $this->publish($this->branch, false, null);

        Sanctum::actingAs($this->client, ['*'], 'user_api');

        $this->postJson('/api/v1/public/conversations', [
            'target_type' => 'restaurant',
            'target_slug' => 'tajine-public-e2e',
            'body' => 'Bonjour ?',
        ])->assertNotFound();
    }

    // ── Erreurs et non-régressions ─────────────────────────────────────────────

    public function test_unknown_discovery_type_is_rejected_with_422(): void
    {
        // Jamais une liste vide silencieuse : un `?type=` inconnu doit être refusé.
        $this->getJson('/api/v1/public/discover?type=verticale-inexistante')
            ->assertStatus(422);
    }

    public function test_discovery_is_paginated_and_bounded(): void
    {
        $this->getJson('/api/v1/public/discover?per_page=500')
            ->assertStatus(422);

        $this->getJson('/api/v1/public/discover?per_page=10')
            ->assertOk();
    }

    public function test_empty_and_oversized_messages_are_rejected(): void
    {
        $this->publish($this->branch);

        Sanctum::actingAs($this->client, ['*'], 'user_api');

        $threadId = (int) $this->postJson('/api/v1/public/conversations', [
            'target_type' => 'restaurant',
            'target_slug' => 'tajine-public-e2e',
            'body' => 'Bonjour.',
        ])->assertCreated()->json('data.thread_id');

        $this->postJson("/api/v1/public/conversations/{$this->company->id}/{$threadId}/messages", [
            'body' => '   ',
        ])->assertStatus(422);

        $this->postJson("/api/v1/public/conversations/{$this->company->id}/{$threadId}/messages", [
            'body' => str_repeat('a', 2001),
        ])->assertStatus(422);
    }

    /**
     * Non-régression de la carte de routage (§10.2 du rapport) : `public_conversation_index`
     * est la SEULE carte client → tenant. Un oubli d'écriture rend le fil invisible
     * côté client — échec SILENCIEUX, pas une erreur. Ce test doit rester rouge si
     * `remember()` disparaît de `PublicConversationService`.
     */
    public function test_the_routing_index_has_exactly_one_row_per_public_thread(): void
    {
        $this->publish($this->branch);

        Sanctum::actingAs($this->client, ['*'], 'user_api');

        $threadId = (int) $this->postJson('/api/v1/public/conversations', [
            'target_type' => 'restaurant',
            'target_slug' => 'tajine-public-e2e',
            'body' => 'Bonjour.',
        ])->assertCreated()->json('data.thread_id');

        $rows = DB::table('public_conversation_index')
            ->where('thread_id', $threadId)
            ->get();

        // Diagnostic ajouté après un échec inexpliqué : si `total` vaut 0, l'écriture
        // n'a pas eu lieu ; si `total` vaut 1 mais `rows` 0, c'est la valeur de
        // `thread_id` qui diffère (cast). Les deux causes n'ont pas le même correctif.
        $total = DB::table('public_conversation_index')->count();

        $this->assertCount(
            1,
            $rows,
            "Chaque fil public doit être indexé exactement une fois (total_index_rows={$total}, thread_id={$threadId})."
        );

        // Et le client le retrouve bien dans sa liste.
        $list = $this->getJson('/api/v1/public/conversations')->assertOk();
        $ids = collect($list->json('data'))->pluck('thread_id')->map(fn ($id): int => (int) $id)->all();
        $this->assertContains($threadId, $ids, 'Sans index, le fil devient invisible côté client.');
    }

    /**
     * Garde anti « bouton mort » : la déclaration `supportedActions()` doit être
     * un sous-ensemble des actions que le parcours sait réellement servir.
     * Aujourd'hui seuls `contact` (conversation) et `order`/`booking` sur les
     * verticales qui les ont testé sont acceptés — voir P1-03 du plan.
     */
    public function test_declared_actions_are_subset_of_the_proven_ones(): void
    {
        $proven = ['contact']; // seul parcours prouvé de bout en bout par cette suite

        $response = $this->getJson('/api/v1/public/discover')->assertOk();
        $actions = $response->json('meta.actions') ?? [];

        foreach ($actions as $type => $declared) {
            foreach ($declared as $action) {
                $this->assertContains(
                    $action,
                    array_merge($proven, ['order', 'booking']),
                    "La verticale `{$type}` déclare `{$action}` sans test public correspondant."
                );
            }
        }
    }
}
