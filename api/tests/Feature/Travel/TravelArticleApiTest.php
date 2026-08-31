<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelArticle;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-901 (#6104) — Articles & catégories (CRUD, statuts, modération).
 *
 * Couvre : CRUD articles + catégories, catégories UNIQUES par tenant
 * (critère d'acceptation), publication contrôlée et isolation cross-tenant.
 */
class TravelArticleApiTest extends TestCase
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

    public function test_principal_can_create_category_and_article(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $category = $this->postJson('/api/v1/travel/community/categories', [
            'code' => 'ACTUALITES',
            'name' => 'Actualités',
        ])->assertStatus(201)->json('data');

        $this->postJson('/api/v1/travel/community/articles', [
            'category_id' => $category['id'],
            'title' => 'Nouvelle ligne Douala–Yaoundé',
            'body' => 'La compagnie XYZ ouvre une nouvelle ligne quotidienne entre Douala et Yaoundé.',
            'status' => 'published',
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.title', 'Nouvelle ligne Douala–Yaoundé');
    }

    public function test_duplicate_category_code_is_rejected(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->postJson('/api/v1/travel/community/categories', ['code' => 'ACTU', 'name' => 'A'])
            ->assertStatus(201);

        // Unicité applicative du code par tenant → 500 ? Non : 422 attendu.
        $this->postJson('/api/v1/travel/community/categories', ['code' => 'ACTU', 'name' => 'B'])
            ->assertStatus(422);
    }

    public function test_article_publish_is_controlled(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $article = $this->postJson('/api/v1/travel/community/articles', [
            'title' => 'Brouillon',
            'body' => 'Contenu du brouillon destiné à la publication.',
        ])->assertStatus(201)->json('data');

        $this->assertSame('draft', $article['status']);
        $this->assertNull($article['published_at']);

        $this->postJson("/api/v1/travel/community/articles/{$article['id']}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.published_at', fn ($v) => $v !== null);
    }

    public function test_article_of_another_tenant_returns_404(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($companyA);
        $this->principal($companyA);

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $articleId = app(TenantManager::class)->withinTenant($companyB, function (): int {
            return TravelArticle::factory()->create()->id;
        });

        $this->getJson("/api/v1/travel/community/articles/{$articleId}")->assertStatus(404);
    }

    public function test_moderation_sets_status_and_note(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $article = $this->postJson('/api/v1/travel/community/articles', [
            'title' => 'Article signalé',
            'body' => 'Contenu signalé par un utilisateur.',
        ])->assertStatus(201)->json('data');

        $this->postJson("/api/v1/travel/community/articles/{$article['id']}/moderate", [
            'status' => 'reported',
            'moderation_note' => 'Contenu non conforme',
        ])->assertOk()
            ->assertJsonPath('data.status', 'reported')
            ->assertJsonPath('data.moderation_note', 'Contenu non conforme');
    }
}
