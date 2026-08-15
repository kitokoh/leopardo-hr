<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProvisionDemoTenantJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #3951 — double POST trial/signup (guided_trial) : la 2e requête ne
 * doit pas créer une 2e ligne pending ni dispatcher un 2e job (2 tenants
 * sandbox). La ligne existante est réutilisée (même token, idempotent).
 */
class TrialProvisioningDedupTest extends TestCase
{
    use RefreshTenantDatabase;

    private function guidedTrialPayload(string $email): array
    {
        return [
            'email' => $email,
            'company' => 'NewTech Algeria',
            'first_name' => 'Ali',
            'last_name' => 'Ben',
            'country' => 'DZ',
            'requestedWorkflow' => 'guided_trial',
        ];
    }

    public function test_double_guided_trial_post_reuses_the_pending_row(): void
    {
        Queue::fake();

        $email = 'guided-'.uniqid().'@example.com';

        $first = $this->postJson('/api/v1/trial/signup', $this->guidedTrialPayload($email));
        $first->assertStatus(200)->assertJsonPath('success', true);

        $firstToken = $first->json('data.provisioning_token');
        $this->assertNotNull($firstToken);

        // Second POST : même email, toujours pending → même token, PAS de
        // nouvelle ligne, PAS de second job.
        $second = $this->postJson('/api/v1/trial/signup', $this->guidedTrialPayload($email));
        $second->assertStatus(200)->assertJsonPath('success', true);
        $this->assertSame($firstToken, $second->json('data.provisioning_token'));

        $this->assertSame(1, DB::table('trial_provisionings')
            ->where('email', $email)
            ->where('status', 'pending')
            ->count());

        Queue::assertPushed(ProvisionDemoTenantJob::class, 1);
    }

    public function test_ready_row_allows_a_new_provisioning_cycle(): void
    {
        Queue::fake();

        $email = 'ready-'.uniqid().'@example.com';

        $first = $this->postJson('/api/v1/trial/signup', $this->guidedTrialPayload($email));
        $firstToken = $first->json('data.provisioning_token');

        // La ligne passe à ready (provisioning terminé) — l'index partiel ne
        // couvre que status='pending' : un nouveau cycle est possible.
        DB::table('trial_provisionings')
            ->where('email', $email)
            ->update(['status' => 'ready']);

        $second = $this->postJson('/api/v1/trial/signup', $this->guidedTrialPayload($email));
        $second->assertStatus(200);
        $this->assertNotSame($firstToken, $second->json('data.provisioning_token'));

        $this->assertSame(1, DB::table('trial_provisionings')
            ->where('email', $email)
            ->where('status', 'pending')
            ->count());
    }

    public function test_failed_row_allows_a_new_provisioning_cycle(): void
    {
        Queue::fake();

        $email = 'failed-'.uniqid().'@example.com';

        $this->postJson('/api/v1/trial/signup', $this->guidedTrialPayload($email));

        DB::table('trial_provisionings')
            ->where('email', $email)
            ->update(['status' => 'failed']);

        $retry = $this->postJson('/api/v1/trial/signup', $this->guidedTrialPayload($email));
        $retry->assertStatus(200);

        $this->assertSame(1, DB::table('trial_provisionings')
            ->where('email', $email)
            ->where('status', 'pending')
            ->count());
    }
}
