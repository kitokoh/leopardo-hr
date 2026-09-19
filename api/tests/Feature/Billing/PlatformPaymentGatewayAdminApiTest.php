<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Core\Tenant\Domain\Enums\PlatformRole;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Billing\Domain\Models\PaymentGatewaySetting;
use App\Modules\Billing\Infrastructure\Services\GatewaySettingsService;
use App\Modules\Billing\Infrastructure\Services\StripeService;
use App\Shared\Contracts\Payments\PaymentGatewayConfigProviderInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #7726 (BC-21 BILLING) — configuration des passerelles de paiement depuis
 * l'admin plateforme : stockage chiffré BDD, précédence BDD → fallback env.
 *
 * Contrat verrouillé ici :
 *   1. GET liste les passerelles avec leur SOURCE effective (env sans ligne
 *      BDD) et ne renvoie JAMAIS un secret en clair (masque uniquement) ;
 *   2. PUT chiffre les secrets au repos (la colonne ne contient pas le clair)
 *      et la config BDD PRIME sur l'env (précédence) sans redéploiement ;
 *   3. secrets write-only : un PUT sans champ secret conserve la valeur ;
 *   4. fallback env intact : sans ligne BDD, `GatewaySettingsService` renvoie
 *      exactement la config env (comportement historique) ;
 *   5. permission `billing.manage` exigée (rôle support → 403) ;
 *   6. la vérification de signature webhook Stripe utilise le secret BDD
 *      s'il existe.
 */
class PlatformPaymentGatewayAdminApiTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        // La fixture MVP est partielle : la table #7726 n'y figure pas.
        if (! Schema::hasTable('payment_gateway_settings')) {
            Schema::create('payment_gateway_settings', function (Blueprint $table): void {
                $table->id();
                $table->string('gateway', 30)->unique();
                $table->string('mode', 10)->default('test');
                $table->jsonb('config')->nullable();
                $table->text('secrets')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }
        DB::table('payment_gateway_settings')->delete();

        $this->actingAsPlatform(PlatformRole::SuperAdmin);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    private function actingAsPlatform(PlatformRole $role): SuperAdmin
    {
        $account = new SuperAdmin([
            'name' => 'Platform '.$role->value,
            'email' => $role->value.'-gateways@leopardo.test',
        ]);
        $account->forceFill([
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
            'platform_role' => $role->value,
        ])->save();

        Sanctum::actingAs($account, ['*'], 'super_admin_api');

        return $account;
    }

    private function freshService(): GatewaySettingsService
    {
        // Instance fraîche : le memo par requête du singleton ne doit pas
        // masquer la précédence testée.
        return new GatewaySettingsService;
    }

    public function test_index_reports_env_source_and_never_returns_secrets_in_clear(): void
    {
        Config::set('services.stripe.secret', 'sk_test_env_secret_key_9876');
        Config::set('services.stripe.webhook_secret', 'whsec_env_webhook_4321');

        $response = $this->getJson('/api/v1/platform/billing/gateways');

        $response->assertOk();

        /** @var list<array<string, mixed>> $rows */
        $rows = $response->json('data.items');
        $items = collect($rows);
        $stripe = $items->firstWhere('gateway', 'stripe');

        $this->assertNotNull($stripe);
        $this->assertSame('env', $stripe['source']);
        $this->assertTrue($stripe['secrets']['secret_key']['configured']);
        $this->assertSame('sk_test_••••9876', $stripe['secrets']['secret_key']['mask']);

        // Jamais de secret en clair, nulle part dans la réponse.
        $raw = $response->getContent();
        $this->assertIsString($raw);
        $this->assertStringNotContainsString('sk_test_env_secret_key_9876', $raw);
        $this->assertStringNotContainsString('whsec_env_webhook_4321', $raw);
    }

    public function test_update_encrypts_secrets_at_rest_and_database_takes_precedence_over_env(): void
    {
        Config::set('services.stripe.secret', 'sk_test_env_secret_key_9876');
        Config::set('services.stripe.webhook_secret', 'whsec_env_webhook_4321');

        $response = $this->putJson('/api/v1/platform/billing/gateways', [
            'gateway' => 'stripe',
            'mode' => 'live',
            'is_active' => true,
            'config' => ['price_pilot' => 'price_db_pilot'],
            'secrets' => [
                'secret_key' => 'sk_live_db_secret_key_1234',
                'webhook_secret' => 'whsec_db_webhook_5678',
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.source', 'database');
        $response->assertJsonPath('data.secrets.secret_key.mask', 'sk_live_••••1234');

        // Chiffré AU REPOS : la colonne ne contient jamais le clair.
        $row = DB::table('payment_gateway_settings')->where('gateway', 'stripe')->first();
        $this->assertNotNull($row);
        $this->assertIsString($row->secrets);
        $this->assertStringNotContainsString('sk_live_db_secret_key_1234', $row->secrets);
        $this->assertStringNotContainsString('whsec_db_webhook_5678', $row->secrets);

        // La réponse API ne contient pas le clair non plus.
        $raw = $response->getContent();
        $this->assertIsString($raw);
        $this->assertStringNotContainsString('sk_live_db_secret_key_1234', $raw);

        // Précédence BDD → env : le service résout la clé BDD, pas l'env.
        $resolved = $this->freshService()->resolve('stripe');
        $this->assertSame('database', $resolved['source']);
        $this->assertSame('sk_live_db_secret_key_1234', $resolved['secret_key']);
        $this->assertSame('whsec_db_webhook_5678', $resolved['webhook_secret']);
        $this->assertSame('price_db_pilot', $resolved['price_pilot']);
    }

    public function test_secrets_are_write_only_an_update_without_secret_keeps_stored_value(): void
    {
        $this->putJson('/api/v1/platform/billing/gateways', [
            'gateway' => 'stripe',
            'mode' => 'test',
            'secrets' => ['secret_key' => 'sk_test_db_initial_0001'],
        ])->assertOk();

        // Second PUT sans secret (édition du mode uniquement).
        $this->putJson('/api/v1/platform/billing/gateways', [
            'gateway' => 'stripe',
            'mode' => 'live',
            'secrets' => ['secret_key' => ''],
        ])->assertOk();

        $resolved = $this->freshService()->resolve('stripe');
        $this->assertSame('sk_test_db_initial_0001', $resolved['secret_key']);
        $this->assertSame('live', $resolved['mode']);
    }

    public function test_env_fallback_is_intact_without_database_row(): void
    {
        Config::set('services.stripe.secret', 'sk_test_env_only_key_1111');
        Config::set('services.stripe.webhook_secret', 'whsec_env_only_2222');
        Config::set('services.stripe.price_pilot', 'price_env_pilot');

        $resolved = $this->freshService()->resolve('stripe');

        $this->assertSame('env', $resolved['source']);
        $this->assertSame('sk_test_env_only_key_1111', $resolved['secret_key']);
        $this->assertSame('whsec_env_only_2222', $resolved['webhook_secret']);
        $this->assertSame('price_env_pilot', $resolved['price_pilot']);
    }

    public function test_billing_manage_permission_is_required(): void
    {
        // Le rôle support ne porte pas billing.manage.
        $this->actingAsPlatform(PlatformRole::Support);

        $this->getJson('/api/v1/platform/billing/gateways')->assertForbidden();
        $this->putJson('/api/v1/platform/billing/gateways', ['gateway' => 'stripe'])->assertForbidden();
        $this->postJson('/api/v1/platform/billing/gateways/stripe/test')->assertForbidden();
    }

    public function test_stripe_webhook_signature_verification_uses_database_secret(): void
    {
        // Env : un secret DIFFÉRENT — si le service utilisait l'env, la
        // signature construite avec le secret BDD serait rejetée.
        Config::set('services.stripe.webhook_secret', 'whsec_env_other_secret');

        $this->putJson('/api/v1/platform/billing/gateways', [
            'gateway' => 'stripe',
            'secrets' => ['webhook_secret' => 'whsec_db_webhook_secret_ok'],
        ])->assertOk();

        $payload = json_encode(['id' => 'evt_1', 'type' => 'checkout.session.completed', 'data' => ['object' => []]]);
        $this->assertIsString($payload);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_db_webhook_secret_ok');
        $header = 't='.$timestamp.',v1='.$signature;

        $service = new StripeService(app(PaymentGatewayConfigProviderInterface::class));
        // Le singleton peut avoir mémorisé l'état pré-PUT : flush explicite.
        app(PaymentGatewayConfigProviderInterface::class)->flush('stripe');
        $service = new StripeService(app(PaymentGatewayConfigProviderInterface::class));

        $event = $service->verifyWebhookSignature($payload, $header);
        $this->assertIsArray($event);
        $this->assertSame('checkout.session.completed', $event['type']);

        // Contre-preuve : une signature construite avec le secret ENV est rejetée.
        $badSignature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_env_other_secret');
        $this->assertNull($service->verifyWebhookSignature($payload, 't='.$timestamp.',v1='.$badSignature));
    }

    public function test_connection_test_pings_gateway_without_leaking_keys(): void
    {
        Http::fake(['api.stripe.com/v1/account' => Http::response(['id' => 'acct_1'], 200)]);

        $this->putJson('/api/v1/platform/billing/gateways', [
            'gateway' => 'stripe',
            'secrets' => ['secret_key' => 'sk_test_db_ping_key_7777'],
        ])->assertOk();

        $response = $this->postJson('/api/v1/platform/billing/gateways/stripe/test');

        $response->assertOk();
        $response->assertJsonPath('data.ok', true);
        $response->assertJsonPath('data.status', 'OK');
        $response->assertJsonPath('data.source', 'database');

        $raw = $response->getContent();
        $this->assertIsString($raw);
        $this->assertStringNotContainsString('sk_test_db_ping_key_7777', $raw);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.stripe.com/v1/account'
                && $request->header('Authorization')[0] === 'Bearer sk_test_db_ping_key_7777';
        });
    }
}
