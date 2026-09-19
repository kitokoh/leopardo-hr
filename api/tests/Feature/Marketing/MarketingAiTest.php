<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\AI\DTOs\AIResponse;
use App\AI\LLMClient;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Marketing\Domain\Models\PostPublication;
use App\Modules\Marketing\Domain\Models\SocialAccount;
use App\Modules\Marketing\Domain\Models\SocialPost;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #7753 — IA marketing (suggestion de post + bilan hebdomadaire).
 *
 * Couvre : suggestion via LLM scripté, validation du brief, 503 explicite
 * quand le LLM échoue, bilan hebdo (agrégats + synthèse IA + fallback
 * déterministe jamais bloquant), RBAC (employé ordinaire refusé).
 */
class MarketingAiTest extends TestCase
{
    use RefreshTenantDatabase;

    private function marketingManager(Company $company): Employee
    {
        return Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'marketing',
        ]);
    }

    private function stubLlm(?string $content, ?string $error = null): void
    {
        $this->app->instance(LLMClient::class, new class($content, $error) implements LLMClient
        {
            public function __construct(private readonly ?string $content, private readonly ?string $error) {}

            public function chat(array $messages, array $tools = []): AIResponse
            {
                return new AIResponse(content: $this->content ?? '', model: 'stub', error: $this->error);
            }

            public function provider(): string
            {
                return 'stub';
            }
        });
    }

    public function test_suggest_post_returns_ai_suggestion(): void
    {
        $company = Company::factory()->create();
        $this->stubLlm('Découvrez notre nouvelle offre ! #promo');

        Sanctum::actingAs($this->marketingManager($company));

        $this->postJson('/api/v1/marketing/ai/suggest-post', [
            'brief' => 'Promotion de rentrée sur nos services',
            'platforms' => ['linkedin', 'facebook_page'],
            'tone' => 'enthousiaste',
        ])
            ->assertOk()
            ->assertJsonPath('data.suggestion', 'Découvrez notre nouvelle offre ! #promo')
            ->assertJsonPath('data.platforms', ['linkedin', 'facebook_page']);
    }

    public function test_suggest_post_requires_brief(): void
    {
        $company = Company::factory()->create();
        $this->stubLlm('x');

        Sanctum::actingAs($this->marketingManager($company));

        $this->postJson('/api/v1/marketing/ai/suggest-post', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['brief']);
    }

    public function test_suggest_post_returns_503_when_llm_fails(): void
    {
        $company = Company::factory()->create();
        $this->stubLlm(null, 'provider down');

        Sanctum::actingAs($this->marketingManager($company));

        $this->postJson('/api/v1/marketing/ai/suggest-post', [
            'brief' => 'Promotion de rentrée',
        ])
            ->assertStatus(503)
            ->assertJsonPath('error', 'MARKETING_AI_UNAVAILABLE');
    }

    public function test_weekly_report_aggregates_and_uses_ai_summary(): void
    {
        $company = Company::factory()->create();
        $this->stubLlm('Bonne semaine : 2 publications réussies. Continuez sur LinkedIn.');

        /** @var SocialAccount $account */
        $account = SocialAccount::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'provider' => 'ayrshare',
            'provider_profile_ref' => 'profile-key-abc',
            'status' => 'active',
        ]);

        /** @var SocialPost $post */
        $post = SocialPost::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'social_account_id' => $account->id,
            'content' => 'Post publié',
            'target_platforms' => ['linkedin'],
            'status' => SocialPost::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);

        PostPublication::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'social_post_id' => $post->id,
            'social_account_id' => $account->id,
            'platform' => 'linkedin',
            'status' => PostPublication::STATUS_SUCCESS,
            'published_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($this->marketingManager($company));

        $this->getJson('/api/v1/marketing/reports/weekly')
            ->assertOk()
            ->assertJsonPath('data.stats.posts_published', 1)
            ->assertJsonPath('data.stats.publications_by_platform.linkedin', 1)
            ->assertJsonPath('data.summary_source', 'ai')
            ->assertJsonPath('data.summary', 'Bonne semaine : 2 publications réussies. Continuez sur LinkedIn.');
    }

    public function test_weekly_report_falls_back_when_llm_fails(): void
    {
        $company = Company::factory()->create();
        $this->stubLlm(null, 'provider down');

        Sanctum::actingAs($this->marketingManager($company));

        $response = $this->getJson('/api/v1/marketing/reports/weekly')
            ->assertOk()
            ->assertJsonPath('data.summary_source', 'fallback')
            ->assertJsonPath('data.stats.posts_published', 0);

        $summary = $response->json('data.summary');
        $this->assertIsString($summary);
        $this->assertStringContainsString('7 derniers jours', $summary);
    }

    public function test_ordinary_employee_is_forbidden(): void
    {
        $company = Company::factory()->create();
        $this->stubLlm('x');

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        Sanctum::actingAs($employee);

        $this->postJson('/api/v1/marketing/ai/suggest-post', ['brief' => 'test brief'])
            ->assertStatus(403);
        $this->getJson('/api/v1/marketing/reports/weekly')->assertStatus(403);
    }
}
