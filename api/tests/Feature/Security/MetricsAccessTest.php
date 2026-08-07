<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * GET /api/v1/metrics exposes platform-wide internals (PHP/Laravel versions,
 * cache/queue/db drivers, tenant and employee counts) — it must not be
 * served anonymously. See issue #1466.
 */
class MetricsAccessTest extends TestCase
{
    /** @test */
    public function metrics_requires_authentication(): void
    {
        $this->getJson('/api/v1/metrics')->assertUnauthorized();
    }

    /** @test */
    public function metrics_requires_super_admin(): void
    {
        $superAdmin = SuperAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => fake()->unique()->safeEmail(),
            'password_hash' => Hash::make('password123'),
        ]);

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');

        $this->getJson('/api/v1/metrics')->assertOk();
    }

    /** @test */
    public function health_remains_public_for_render_healthchecks(): void
    {
        // Render deploy hooks consume /api/v1/health — never protect it.
        $this->getJson('/api/v1/health')->assertOk();
    }
}
