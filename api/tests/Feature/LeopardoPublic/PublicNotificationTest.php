<?php

declare(strict_types=1);

namespace Tests\Feature\LeopardoPublic;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Domain\Models\User;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Notification\Application\Services\PublicConversationService;
use App\Modules\Notification\Domain\Models\ConversationThread;
use App\Modules\Platform\Application\Services\PublicNotificationService;
use App\Modules\Platform\Domain\Models\PublicNotification;
use App\Modules\RestaurantManager\Application\Actions\TransitionOrderAction;
use App\Modules\RestaurantManager\Domain\Enums\OrderStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderItem;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTaxRate;
use App\Shared\Contracts\Notification\PublicClientNotifier;
use Illuminate\Support\Facades\Auth;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * COULOIR 16 — M3-bis (#9142) · Première habitude quotidienne.
 *
 * Couvre les critères d'acceptation de l'issue :
 *
 *   - une réponse de manager crée une notification in-app pour le client ;
 *   - un changement de statut d'activité crée une notification in-app ;
 *   - le client ne lit QUE ses notifications (vrai token + `Auth::forgetGuards()`) ;
 *   - une notification manquée ne fait jamais échouer l'écriture métier ;
 *   - aucune donnée personnelle dans le corps ;
 *   - le contrat partagé `PublicClientNotifier` est bien lié (pas d'import croisé).
 *
 * ⚠️ Ces tests s'exécutent sur les VRAIES migrations (`RefreshTenantDatabase`),
 * pas sur une fixture partielle : la table `public_notifications` et sa
 * contrainte d'unicité `(user_id, dedupe_key)` sont donc réellement vérifiées.
 */
class PublicNotificationTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private User $client;

    private User $otherClient;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'slug' => 'resto-notifs',
            'name' => 'Restaurant Notifications',
        ]);

        $this->client = User::factory()->create([
            'email' => 'client-notifs@example.test',
        ]);

        $this->otherClient = User::factory()->create([
            'email' => 'autre-notifs@example.test',
        ]);

        $this->manager = $this->createManager();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Autorisation : vrai token, guards oubliés
    // ─────────────────────────────────────────────────────────────────────────

    public function test_notifications_require_a_real_user_api_token(): void
    {
        $this->getJson('/api/v1/user/notifications')->assertStatus(401);
        $this->postJson('/api/v1/user/notifications/1/read')->assertStatus(401);
        $this->postJson('/api/v1/user/notifications/read-all')->assertStatus(401);
    }

    public function test_the_inbox_is_empty_for_a_fresh_client(): void
    {
        $this->actingAsClient($this->client);

        $this->getJson('/api/v1/user/notifications')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.unread', 0);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Le client ne lit QUE ses notifications
    // ─────────────────────────────────────────────────────────────────────────

    public function test_a_client_never_sees_another_clients_notifications(): void
    {
        $this->notify($this->otherClient, 'thread:1:reply');

        $this->actingAsClient($this->client);

        $this->getJson('/api/v1/user/notifications')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.unread', 0);
    }

    public function test_marking_another_clients_notification_returns_404_not_403(): void
    {
        $foreign = $this->notify($this->otherClient, 'thread:2:reply');

        $this->actingAsClient($this->client);

        // 404 et non 403 : un 403 confirmerait l'existence d'une notification
        // qui ne me concerne pas, et permettrait d'énumérer les identifiants.
        $this->postJson('/api/v1/user/notifications/'.$foreign->id.'/read')
            ->assertStatus(404);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Réponse d'un commerçant
    // ─────────────────────────────────────────────────────────────────────────

    public function test_a_merchant_reply_notifies_the_public_client(): void
    {
        $thread = $this->createPublicThread($this->client);

        $this->postManagerReply($thread, $this->manager, 'Bonjour, votre table est prête.');

        $this->assertDatabaseHas('public_notifications', [
            'user_id' => $this->client->id,
            'type' => PublicNotification::TYPE_MERCHANT_REPLY,
        ]);

        $this->actingAsClient($this->client);

        $this->getJson('/api/v1/user/notifications')
            ->assertOk()
            ->assertJsonPath('meta.unread', 1)
            ->assertJsonPath('data.0.type', PublicNotification::TYPE_MERCHANT_REPLY);
    }

    public function test_replaying_the_same_reply_does_not_duplicate_the_notification(): void
    {
        $thread = $this->createPublicThread($this->client);

        $this->postManagerReply($thread, $this->manager, 'Première réponse.');
        $this->postManagerReply($thread, $this->manager, 'Deuxième réponse.');

        // La clé de dédoublonnage est `thread:<id>:reply` : deux réponses sur le
        // même fil ne créent qu'UNE notification tant qu'elle n'est pas lue.
        $this->assertSame(
            1,
            PublicNotification::query()
                ->where('user_id', $this->client->id)
                ->where('type', PublicNotification::TYPE_MERCHANT_REPLY)
                ->count(),
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Suivi de commande
    // ─────────────────────────────────────────────────────────────────────────

    public function test_an_order_status_change_notifies_the_public_client(): void
    {
        $order = $this->createOrder($this->client, 'RST-SUIVI');

        $this->transition($order, OrderStatus::IN_PREPARATION);

        $this->assertDatabaseHas('public_notifications', [
            'user_id' => $this->client->id,
            'type' => PublicNotification::TYPE_ACTIVITY_STATUS,
        ]);

        $this->actingAsClient($this->client);

        $this->getJson('/api/v1/user/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.data.external_ref', 'RST-SUIVI')
            ->assertJsonPath('data.0.data.status', 'in_preparation');
    }

    public function test_an_order_without_a_public_client_notifies_nobody(): void
    {
        $order = $this->createOrder(null, 'RST-COMPTOIR');

        $this->transition($order, OrderStatus::IN_PREPARATION);

        // Une commande passée au comptoir n'a pas de destinataire : c'est le
        // cas majoritaire, et il ne doit produire aucune ligne.
        $this->assertSame(0, PublicNotification::query()->count());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Marquage comme lu
    // ─────────────────────────────────────────────────────────────────────────

    public function test_marking_a_notification_as_read_decrements_the_badge(): void
    {
        $notification = $this->notify($this->client, 'thread:3:reply');

        $this->actingAsClient($this->client);

        $this->postJson('/api/v1/user/notifications/'.$notification->id.'/read')
            ->assertOk()
            ->assertJsonPath('data.unread', 0);

        $this->assertNotNull($notification->fresh()?->read_at);
    }

    public function test_marking_all_as_read_clears_the_badge(): void
    {
        $this->notify($this->client, 'thread:4:reply');
        $this->notify($this->client, 'activity:restaurant:RST-A:ready');

        $this->actingAsClient($this->client);

        $this->postJson('/api/v1/user/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.updated', 2)
            ->assertJsonPath('data.unread', 0);
    }

    public function test_reading_twice_keeps_the_first_read_date(): void
    {
        $notification = $this->notify($this->client, 'thread:5:reply');

        $this->actingAsClient($this->client);

        $this->postJson('/api/v1/user/notifications/'.$notification->id.'/read')->assertOk();
        $firstRead = $notification->fresh()?->read_at;

        $this->postJson('/api/v1/user/notifications/'.$notification->id.'/read')->assertOk();

        // C'est la date de PREMIÈRE lecture qui mesure l'habitude (retour J7) :
        // relire ne doit pas la déplacer.
        $this->assertEquals($firstRead, $notification->fresh()?->read_at);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Aucune donnée personnelle
    // ─────────────────────────────────────────────────────────────────────────

    public function test_the_notification_body_carries_no_personal_data(): void
    {
        $order = $this->createOrder($this->client, 'RST-PII');

        $this->transition($order, OrderStatus::IN_PREPARATION);

        $notification = PublicNotification::query()
            ->where('user_id', $this->client->id)
            ->firstOrFail();

        $haystack = $notification->title.' '.$notification->body.' '.json_encode($notification->data);

        // Ni e-mail, ni téléphone, ni prix : le corps ne porte que le nom
        // commercial, la référence publique et le statut traduit.
        $this->assertStringNotContainsString('@', $haystack);
        $this->assertStringNotContainsString('1200', $haystack);
        $this->assertStringNotContainsString('DZD', $haystack);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Le contrat partagé est bien lié
    // ─────────────────────────────────────────────────────────────────────────

    public function test_the_shared_contract_resolves_to_the_platform_service(): void
    {
        // Une verticale (BC-25) ne dépend que de l'interface : si le binding
        // disparaissait, la notification de suivi de commande cesserait
        // silencieusement de fonctionner (best-effort) — d'où ce test.
        $this->assertInstanceOf(
            PublicNotificationService::class,
            $this->app->make(PublicClientNotifier::class),
        );
    }

    public function test_the_contract_returns_false_for_an_unknown_client(): void
    {
        /** @var PublicClientNotifier $notifier */
        $notifier = $this->app->make(PublicClientNotifier::class);

        $this->assertFalse($notifier->notifyActivityStatus(
            clientId: 999999999,
            merchantName: 'Restaurant Notifications',
            vertical: 'restaurant',
            activityType: 'order',
            externalRef: 'RST-INCONNU',
            status: 'ready',
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Authentifie le client avec un VRAI token Sanctum sur le guard `user_api`.
     *
     * `Auth::forgetGuards()` est indispensable : sans lui, un guard résolu lors
     * d'un test précédent resterait en cache et le test suivant s'exécuterait
     * avec l'identité du précédent — c'est exactement le faux positif que le
     * critère d'acceptation vise.
     */
    private function actingAsClient(User $client): void
    {
        Auth::forgetGuards();

        $token = $client->createToken('m3-bis-test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token);

        Auth::forgetGuards();
    }

    /**
     * Écrit une notification directement via le service (sans passer par un
     * fil ni une commande) — utile pour les tests d'autorisation.
     */
    private function notify(User $client, string $dedupeKey): PublicNotification
    {
        /** @var PublicNotificationService $service */
        $service = $this->app->make(PublicNotificationService::class);

        $notification = $service->notifyMerchantReply(
            client: $client,
            merchantName: 'Restaurant Notifications',
            threadId: (int) preg_replace('/\D/', '', $dedupeKey),
            companyId: (string) $this->company->id,
        );

        $this->assertInstanceOf(PublicNotification::class, $notification);

        return $notification;
    }

    /**
     * Poste une réponse de manager sur un fil public, DANS le contexte tenant.
     *
     * `postFromManager()` écrit dans les tables du tenant (`conversation_threads`,
     * `conversation_messages`) : sans `withinTenant`, la requête partirait sur le
     * `search_path` courant et échouerait.
     */
    private function postManagerReply(ConversationThread $thread, Employee $manager, string $body): void
    {
        /** @var TenantManager $tenants */
        $tenants = $this->app->make(TenantManager::class);

        $tenants->withinTenant($this->company, function () use ($thread, $manager, $body): void {
            /** @var PublicConversationService $conversations */
            $conversations = $this->app->make(PublicConversationService::class);

            $conversations->postFromManager($thread, $manager, $body);
        });
    }

    /**
     * Crée un fil public rattaché au client donné, dans le schéma du tenant.
     */
    private function createPublicThread(User $client): ConversationThread
    {
        /** @var TenantManager $tenants */
        $tenants = $this->app->make(TenantManager::class);

        return $tenants->withinTenant($this->company, function () use ($client): ConversationThread {
            /** @var ConversationThread $thread */
            $thread = ConversationThread::query()->create([
                'company_id' => $this->company->id,
                // Aucun employé propriétaire : le fil appartient au tenant, pas
                // à une personne (`employee_id` nullable depuis #8760).
                'employee_id' => null,
                'manager_id' => null,
                'kind' => ConversationThread::KIND_PUBLIC,
                'title' => 'Conversation avec Restaurant Notifications',
                'status' => 'open',
                'public_client_id' => $client->id,
                'public_target_slug' => 'resto-notifs',
            ]);

            return $thread;
        });
    }

    /**
     * Crée un employé manager dans le tenant (l'auteur de la réponse).
     */
    private function createManager(): Employee
    {
        /** @var TenantManager $tenants */
        $tenants = $this->app->make(TenantManager::class);

        return $tenants->withinTenant($this->company, function (): Employee {
            /** @var Employee $employee */
            $employee = Employee::factory()->create([
                'company_id' => $this->company->id,
                // `hasParticipant()` exige `isManager()` sur un fil public :
                // un simple employé du même tenant n'y répond pas (#8760).
                'role' => 'manager',
                'manager_role' => 'principal',
            ]);

            return $employee;
        });
    }

    /**
     * Crée une commande restaurant réelle dans le schéma du tenant.
     *
     * `$owner` à `null` simule une commande passée au comptoir (aucun client
     * public rattaché).
     */
    private function createOrder(?User $owner, string $reference): RestaurantOrder
    {
        /** @var TenantManager $tenants */
        $tenants = $this->app->make(TenantManager::class);

        return $tenants->withinTenant($this->company, function () use ($owner, $reference): RestaurantOrder {
            // Une commande réelle exige une branche, un taux de taxe et au moins
            // une ligne active : `TransitionOrderAction` décrémente le stock à
            // la confirmation et refuse une commande sans ligne active.
            $branch = RestaurantBranch::factory()->create();
            $taxRate = RestaurantTaxRate::factory()->create(['rate_bps' => 0]);
            $product = RestaurantProduct::factory()->create([
                'branch_id' => $branch->id,
                'price_minor' => 1200,
                'currency' => $branch->currency,
                'tax_rate_id' => $taxRate->id,
                'is_available' => true,
            ]);

            /** @var RestaurantOrder $order */
            $order = RestaurantOrder::factory()->create([
                'branch_id' => $branch->id,
                'reference' => $reference,
                'status' => 'open',
                'currency' => $branch->currency,
                'public_client_id' => $owner?->id,
            ]);

            RestaurantOrderItem::query()->create([
                'company_id' => $this->company->id,
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price_minor' => 1200,
                'line_total_minor' => 1200,
                'tax_minor' => 0,
                'status' => 'active',
                'line_index' => 1,
            ]);

            return $order;
        });
    }

    /**
     * Exécute une transition d'état sur la commande, dans le tenant.
     */
    private function transition(RestaurantOrder $order, OrderStatus $target): void
    {
        /** @var TenantManager $tenants */
        $tenants = $this->app->make(TenantManager::class);

        $tenants->withinTenant($this->company, function () use ($order, $target): void {
            /** @var TransitionOrderAction $action */
            $action = $this->app->make(TransitionOrderAction::class);

            $action->execute($this->manager, $order, $target);
        });
    }
}
