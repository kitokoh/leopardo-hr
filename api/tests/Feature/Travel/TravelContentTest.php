<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelArticle;
use App\Modules\TravelAgency\Domain\Models\TravelLike;
use App\Modules\TravelAgency\Domain\Models\TravelRating;
use App\Modules\TravelAgency\Domain\Models\TravelShare;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-901/902/903 (#6104/#6105/#6106) — Articles, commentaires,
 * engagement (likes/partages/notes) : CRUD, statuts/modération, unicité
 * (tenant, article, acteur), agrégats serveur.
 */
class TravelContentTest extends TestCase
{
    use RefreshTenantDatabase;

    private function principal(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function activateTravel(Company $company): void
    {
        $company->setFeature('travelagency', true);
        $company->save();
    }

    private function makeArticle(Company $company, string $status = 'published'): TravelArticle
    {
        return app(TenantManager::class)->withinTenant($company, fn (): TravelArticle => TravelArticle::query()->create([
            'company_id' => $company->id,
            'slug' => 'article-'.uniqid(),
            'title' => 'Safari au Cameroun',
            'body_redacted' => 'Contenu éditorial',
            'status' => $status,
            'author_type' => 'employee',
            'author_id' => 1,
        ]));
    }

    public function test_article_crud_and_moderation(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $created = $this->postJson('/api/v1/travel/articles', [
            'slug' => 'douala-littoral',
            'title' => 'Douala & le Littoral',
            'body_redacted' => 'Guide pratique',
            'status' => 'draft',
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'draft');

        $articleId = (int) $created->json('data.id');

        // Modération → published (horodaté).
        $this->postJson("/api/v1/travel/articles/{$articleId}/moderate", ['status' => 'published'])
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.published_at', fn ($v) => $v !== null);

        // Slug dupliqué → 422.
        $this->postJson('/api/v1/travel/articles', [
            'slug' => 'douala-littoral',
            'title' => 'Doublon',
            'body_redacted' => 'x',
        ])->assertStatus(422);

        $this->deleteJson("/api/v1/travel/articles/{$articleId}")->assertStatus(204);
    }

    public function test_comment_moderation_flow(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);
        $article = $this->makeArticle($company);

        $created = $this->postJson('/api/v1/travel/comments', [
            'article_id' => $article->id,
            'content' => 'Très beau guide',
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');

        $commentId = (int) $created->json('data.id');

        // Signalement → flagged.
        $this->postJson("/api/v1/travel/comments/{$commentId}/report")
            ->assertOk()
            ->assertJsonPath('data.status', 'flagged');

        // Modération → rejected.
        $this->postJson("/api/v1/travel/comments/{$commentId}/moderate", ['status' => 'rejected'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        // Contenu trop long → 422.
        $this->postJson('/api/v1/travel/comments', [
            'article_id' => $article->id,
            'content' => str_repeat('a', 2001),
        ])->assertStatus(422);
    }

    public function test_engagement_uniqueness_and_aggregates(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);
        $article = $this->makeArticle($company);

        // Like 2× → un seul (unicité).
        $this->postJson("/api/v1/travel/articles/{$article->id}/like")->assertOk();
        $this->postJson("/api/v1/travel/articles/{$article->id}/like")->assertOk();

        $likes = app(TenantManager::class)->withinTenant($company, fn (): int => TravelLike::query()->where('article_id', $article->id)->count());
        $this->assertSame(1, $likes);

        // Partage.
        $this->postJson("/api/v1/travel/articles/{$article->id}/share", ['channel' => 'whatsapp'])->assertStatus(201);

        // Note 2× → mise à jour (pas de doublon), agrégat exact.
        $this->postJson("/api/v1/travel/articles/{$article->id}/rate", ['rating' => 4])->assertOk();
        $this->postJson("/api/v1/travel/articles/{$article->id}/rate", ['rating' => 5])->assertOk()
            ->assertJsonPath('data.ratings_count', 1)
            ->assertJsonPath('data.average_rating', 5.0);

        $ratings = app(TenantManager::class)->withinTenant($company, fn (): int => TravelRating::query()->where('article_id', $article->id)->count());
        $this->assertSame(1, $ratings);

        // Agrégats : 1 like, 1 partage, 1 note 5/5.
        $this->getJson("/api/v1/travel/articles/{$article->id}/engagement")
            ->assertOk()
            ->assertJsonPath('data.likes_count', 1)
            ->assertJsonPath('data.shares_count', 1)
            ->assertJsonPath('data.average_rating', 5.0);

        // Unlike → 0.
        $this->postJson("/api/v1/travel/articles/{$article->id}/unlike")->assertOk();
        $shares = app(TenantManager::class)->withinTenant($company, fn (): int => TravelShare::query()->where('article_id', $article->id)->count());
        $this->assertSame(1, $shares);
    }
}
