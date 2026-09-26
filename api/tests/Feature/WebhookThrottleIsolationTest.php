<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\ZktecoDevice;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Audit fiabilité #6555 — les webhooks entrants (passerelles partagées, même
 * IP pour N tenants) et les devices ZKTeco (NAT) doivent avoir des buckets de
 * throttle dédiés : plus de 429 illégitimes croisés.
 */
class WebhookThrottleIsolationTest extends TestCase
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

    public function test_two_webhook_gateways_from_same_ip_do_not_throttle_each_other(): void
    {
        // 60 requêtes vers /webhooks/stripe (sans signature) remplissent le
        // bucket 60/min de CETTE passerelle depuis cette IP ; la 61ᵉ est 429
        // (le limiter fonctionne). La passerelle chargily, MÊME IP, ne doit
        // PAS être throttlée : bucket distinct par passerelle (#6555, #8134).
        $payload = ['event' => 'test'];

        $limit = 60;
        for ($i = 0; $i < $limit; $i++) {
            $response = $this->postJson('/api/v1/webhooks/stripe', $payload);
            $this->assertNotSame(429, $response->status(), "Requête stripe #{$i} throttlée avant la limite.");
        }

        // 61ᵉ requête stripe : le bucket de la passerelle est plein → 429.
        $this->assertSame(429, $this->postJson('/api/v1/webhooks/stripe', $payload)->status());

        // Même IP, autre passerelle : aucun 429 croisé (point du test).
        // Le statut exact (400/422...) dépend du contrôleur (signature).
        $this->assertNotSame(429, $this->postJson('/api/v1/webhooks/chargily', $payload)->status());
    }

    public function test_zkteco_devices_behind_same_ip_have_separate_buckets(): void
    {
        $company = Company::query()->create([
            'name' => 'Company Z',
            'slug' => 'company-z',
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'z@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        foreach (['SN-A', 'SN-B'] as $serial) {
            ZktecoDevice::query()->forceCreate([
                'company_id' => $company->id,
                'serial_number' => $serial,
                'name' => 'Device '.$serial,
                'sync_token_hash' => bcrypt('valid-device-token'),
            ]);
        }

        // 121 requêtes (limite 120/min) sur le device A puis 1 requête sur le
        // device B depuis la même IP : B ne doit PAS être 429 (bucket par
        // serial_number), A doit être 429 après dépassement.
        for ($i = 0; $i < 121; $i++) {
            $this->withHeader('X-Device-Token', 'valid-device-token')
                ->postJson('/api/v1/zkteco/heartbeat/SN-A');
        }

        $overLimit = $this->withHeader('X-Device-Token', 'valid-device-token')
            ->postJson('/api/v1/zkteco/heartbeat/SN-A');
        $this->assertSame(429, $overLimit->status(), 'Le device A doit être throttlé après dépassement de son bucket.');

        $otherDevice = $this->withHeader('X-Device-Token', 'valid-device-token')
            ->postJson('/api/v1/zkteco/heartbeat/SN-B');
        $this->assertNotSame(429, $otherDevice->status(), 'Le device B (même IP) ne doit pas être throttlé par le bucket de A.');
    }
}
