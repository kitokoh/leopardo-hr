<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\AI\DTOs\AIResponse;
use App\AI\LLMClient;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Marketing\Domain\Models\SocialAccount;
use App\Modules\Marketing\Domain\Models\SocialPost;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #7754 — interactions sociales (commentaires des posts publiés).
 *
 * Couvre : lecture des commentaires (Ayrshare GET /comments/{id}), réponse
 * (POST /comments), post non publié → 409, compte social absent → 404,
 * suggestion IA de réponse (LLM scripté, humain dans la boucle), RBAC.
 */
class SocialCommentControllerTest extends TestCase
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

    private function connectedAccount(Company $company): SocialAccount
    {
        return SocialAccount::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'provider' => 'ayrshare',
            'provider_profile_ref' => 'profile-key-abc',
            'status' => 'active',
        ]);
    }

    private function publishedPost(Company $company, SocialAccount $account): SocialPost
    {
        return SocialPost::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'social_account_id' => $account->id,
            'content' => 'Post publié',
            'target_platforms' => ['linkedin', 'facebook_page'],
            'status' => SocialPost::STATUS_PUBLISHED,
            'published_at' => now()->subHour(),
            'provider_post_ref' => 'ayr-post-1',
        ]);
    }

    public function test_index_returns_comments_from_ayrshare(): void
    {
        $company = Company::factory()->create();
        $account = $this->connectedAccount($company);
        $post = $this->publishedPost($company, $account);

        Http::fake([
            'api.ayrshare.com/api/comments/ayr-post-1' => Http::response([
                'linkedin' => [['comment' => 'Super offre !', 'userName' => 'Jane']],
            ], 200),
        ]);

        Sanctum::actingAs($this->marketingManager($company));

        $this->getJson("/api/v1/marketing/social-posts/{$post->id}/comments")
            ->assertOk()
            ->assertJsonPath('data.provider_post_ref', 'ayr-post-1')
            ->assertJsonPath('data.comments.linkedin.0.comment', 'Super offre !');
    }

    public function test_index_on_draft_post_returns_409(): void
    {
        $company = Company::factory()->create();
        $account = $this->connectedAccount($company);

        /** @var SocialPost $draft */
        $draft = SocialPost::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'social_account_id' => $account->id,
            'content' => 'Brouillon',
            'target_platforms' => ['linkedin'],
            'status' => SocialPost::STATUS_DRAFT,
        ]);

        Sanctum::actingAs($this->marketingManager($company));

        $this->getJson("/api/v1/marketing/social-posts/{$draft->id}/comments")
            ->assertStatus(409)
            ->assertJsonPath('error', 'SOCIAL_POST_NOT_PUBLISHED');
    }

    public function test_reply_posts_comment_through_ayrshare(): void
    {
        $company = Company::factory()->create();
        $account = $this->connectedAccount($company);
        $post = $this->publishedPost($company, $account);

        Http::fake([
            'api.ayrshare.com/api/comments' => Http::response(['status' => 'success'], 200),
        ]);

        Sanctum::actingAs($this->marketingManager($company));

        $this->postJson("/api/v1/marketing/social-posts/{$post->id}/comments/reply", [
            'comment' => 'Merci pour votre retour !',
            'platforms' => ['linkedin'],
        ])->assertStatus(201);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/api/comments')
                && $request['id'] === 'ayr-post-1'
                && $request['platforms'] === ['linkedin']
                && $request['comment'] === 'Merci pour votre retour !'
                && $request->hasHeader('Profile-Key', 'profile-key-abc');
        });
    }

    public function test_reply_without_connected_account_returns_404(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        // Le post existe mais l'entreprise B n'a pas de compte social.
        $account = $this->connectedAccount($companyA);
        $this->publishedPost($companyA, $account);

        /** @var SocialPost $postB */
        $postB = SocialPost::withoutGlobalScopes()->create([
            'company_id' => $companyB->id,
            'social_account_id' => $account->id,
            'content' => 'Post B',
            'target_platforms' => ['linkedin'],
            'status' => SocialPost::STATUS_PUBLISHED,
            'published_at' => now(),
            'provider_post_ref' => 'ayr-post-b',
        ]);

        Sanctum::actingAs($this->marketingManager($companyB));

        $this->postJson("/api/v1/marketing/social-posts/{$postB->id}/comments/reply", [
            'comment' => 'Réponse',
        ])->assertStatus(404)
            ->assertJsonPath('error', 'SOCIAL_ACCOUNT_NOT_FOUND');
    }

    public function test_suggest_reply_returns_ai_suggestion(): void
    {
        $company = Company::factory()->create();
        $account = $this->connectedAccount($company);
        $post = $this->publishedPost($company, $account);

        $this->app->instance(LLMClient::class, new class implements LLMClient
        {
            public function chat(array $messages, array $tools = []): AIResponse
            {
                return new AIResponse(content: 'Merci Jane, ravi que l\'offre vous plaise !', model: 'stub');
            }

            public function provider(): string
            {
                return 'stub';
            }
        });

        Sanctum::actingAs($this->marketingManager($company));

        $this->postJson("/api/v1/marketing/social-posts/{$post->id}/comments/suggest-reply", [
            'comment' => 'Super offre !',
            'platform' => 'linkedin',
        ])
            ->assertOk()
            ->assertJsonPath('data.suggestion', 'Merci Jane, ravi que l\'offre vous plaise !');
    }

    public function test_ordinary_employee_is_forbidden(): void
    {
        $company = Company::factory()->create();
        $account = $this->connectedAccount($company);
        $post = $this->publishedPost($company, $account);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        Sanctum::actingAs($employee);

        $this->getJson("/api/v1/marketing/social-posts/{$post->id}/comments")->assertStatus(403);
    }
}
