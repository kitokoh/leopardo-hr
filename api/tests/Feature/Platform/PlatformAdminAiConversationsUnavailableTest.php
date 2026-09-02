<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #6690 — GET /admin/ai/conversations avec la table `ai_conversations`
 * présente (IA activée) doit répondre 200. Valide aussi le fix
 * `jsonb_array_length` : la version `json_array_length` n'existe pas pour
 * jsonb (SQLSTATE 42883) → 500 sur chaque requête, même table présente.
 * (Le mapping 403 « feature indisponible » est testé en unitaire dans
 * PlatformAdminAiConversationErrorMappingTest.)
 */
class PlatformAdminAiConversationsUnavailableTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var SuperAdmin $superAdmin */
        $superAdmin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => 'admin-ai-conv@leopardo.test',
        ]);
        $superAdmin->forceFill(['password_hash' => Hash::make('password123')])->save();

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');
    }

    public function test_ai_conversations_returns_200_when_table_exists(): void
    {
        $this->getJson('/api/v1/admin/ai/conversations')
            ->assertOk()
            ->assertJsonPath('data', []);
    }
}
