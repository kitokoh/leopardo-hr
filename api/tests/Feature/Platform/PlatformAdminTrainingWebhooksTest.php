<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Billing\Domain\Models\WebhookEndpoint;
use App\Modules\HR\Domain\Models\TrainingCourse;
use App\Modules\HR\Domain\Models\TrainingSession;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #2634 — la console super-admin doit disposer d'équivalents /admin
 * pour Training (sessions/enrollments) et Webhooks (CRUD + test), sinon les
 * vues Training/Webhooks du SPA répondent 401.
 */
class PlatformAdminTrainingWebhooksTest extends TestCase
{
    use RefreshTenantDatabase;

    private SuperAdmin $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var SuperAdmin $superAdmin */
        $superAdmin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => 'admin-training-webhooks@leopardo.test',
        ]);
        $superAdmin->forceFill(['password_hash' => Hash::make('password123')])->save();
        $this->superAdmin = $superAdmin;

        Sanctum::actingAs($this->superAdmin, ['*'], 'super_admin_api');
    }

    public function test_admin_training_sessions_is_cross_tenant(): void
    {
        /** @var \App\Core\Tenant\Domain\Models\Company $company */
        $company = Company::factory()->create();
        $course = TrainingCourse::query()->create([
            'company_id' => $company->id,
            'title' => 'Formation Secourisme',
        ]);

        TrainingSession::query()->create([
            'company_id' => $company->id,
            'training_course_id' => $course->id,
            'start_date' => now()->addDays(7),
            'end_date' => now()->addDays(8),
            'status' => 'planned',
        ]);

        $this->getJson('/api/v1/admin/training/sessions')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_admin_webhooks_list_is_cross_tenant(): void
    {
        /** @var \App\Core\Tenant\Domain\Models\Company $company */
        $company = Company::factory()->create();

        WebhookEndpoint::query()->create([
            'company_id' => $company->id,
            'url' => 'https://example.com/hook',
            'events' => ['employee.created'],
            'secret' => 'secret-test',
            'active' => true,
        ]);

        $this->getJson('/api/v1/admin/webhooks')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_admin_webhook_create_requires_company(): void
    {
        $this->postJson('/api/v1/admin/webhooks', [
            'url' => 'https://example.com/hook',
            'events' => ['employee.created'],
        ])->assertUnprocessable();

        /** @var \App\Core\Tenant\Domain\Models\Company $company */
        $company = Company::factory()->create();

        $this->postJson('/api/v1/admin/webhooks', [
            'company_id' => $company->id,
            'url' => 'https://example.com/hook',
            'events' => ['employee.created'],
        ])->assertCreated()
            ->assertJsonPath('data.url', 'https://example.com/hook');
    }

    public function test_admin_webhook_test_dispatches_event(): void
    {
        // #6550 (PR #6628) : DispatchWebhook rethrow sur non-2xx — le job de
        // test ne doit JAMAIS taper le vrai réseau (example.com renvoie 405).
        Http::fake([
            'example.com/*' => Http::response('ok', 200),
        ]);

        /** @var \App\Core\Tenant\Domain\Models\Company $company */
        $company = Company::factory()->create();

        $webhook = WebhookEndpoint::query()->create([
            'company_id' => $company->id,
            'url' => 'https://example.com/hook',
            'events' => ['webhook.test'],
            'secret' => 'secret-test',
            'active' => true,
        ]);

        $this->postJson("/api/v1/admin/webhooks/{$webhook->id}/test")
            ->assertOk()
            ->assertJsonPath('message', 'Webhook test event dispatched.');
    }

    public function test_admin_webhook_delete_returns_204(): void
    {
        /** @var \App\Core\Tenant\Domain\Models\Company $company */
        $company = Company::factory()->create();

        $webhook = WebhookEndpoint::query()->create([
            'company_id' => $company->id,
            'url' => 'https://example.com/hook',
            'events' => ['employee.created'],
            'secret' => 'secret-test',
            'active' => true,
        ]);

        $this->deleteJson("/api/v1/admin/webhooks/{$webhook->id}")->assertNoContent();
    }
}
