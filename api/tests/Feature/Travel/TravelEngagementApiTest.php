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
 * TRAVEL-903 (#6106) — Likes / partages / notes.
 *
 * Couvre le critère d'acceptation : UN acteur = UN like par cible ;
 * une seule note par acteur/cible ; agrégats dérivés corrects.
 */
class TravelEngagementApiTest extends TestCase
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

    private function articleId(Company $company): int
    {
        return app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelArticle::factory()->published()->create()->id;
        });
    }

    public function test_one_actor_one_like_per_target(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $articleId = $this->articleId($company);

        // Deux clics de like (idempotent) → toujours un seul like.
        $this->postJson("/api/v1/travel/community/articles/{$articleId}/like")->assertOk();
        $this->postJson("/api/v1/travel/community/articles/{$articleId}/like")->assertOk();

        $this->getJson("/api/v1/travel/community/articles/{$articleId}/engagement")
            ->assertOk()
            ->assertJsonPath('data.likes_count', 1);

        // Unlike → 0.
        $this->postJson("/api/v1/travel/community/articles/{$articleId}/unlike")->assertOk();
        $this->getJson("/api/v1/travel/community/articles/{$articleId}/engagement")
            ->assertOk()
            ->assertJsonPath('data.likes_count', 0);
    }

    public function test_rating_is_unique_per_actor_and_aggregated(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $articleId = $this->articleId($company);

        $this->postJson("/api/v1/travel/community/articles/{$articleId}/rate", ['stars' => 4])->assertOk();
        $this->postJson("/api/v1/travel/community/articles/{$articleId}/rate", ['stars' => 5])->assertOk();

        // Une seule note (mise à jour), moyenne = 5.
        $this->getJson("/api/v1/travel/community/articles/{$articleId}/engagement")
            ->assertOk()
            ->assertJsonPath('data.ratings_count', 1)
            ->assertJsonPath('data.rating_avg', 5);
    }

    public function test_share_is_traced(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $articleId = $this->articleId($company);

        $this->postJson("/api/v1/travel/community/articles/{$articleId}/share", ['channel' => 'whatsapp'])
            ->assertOk()
            ->assertJsonPath('data.shared', true);
    }
}
