<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelArticle;
use App\Modules\TravelAgency\Domain\Models\TravelComment;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-902 (#6105) — Commentaires (CRUD, modération, signalement).
 *
 * Couvre : contenu BORNÉ (3..1000, critère d'acceptation), publication
 * modérée (pending → approved), signalement TRACÉ une seule fois.
 */
class TravelCommentApiTest extends TestCase
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

    public function test_comment_content_is_bounded(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $articleId = $this->articleId($company);

        $this->postJson("/api/v1/travel/community/articles/{$articleId}/comments", [
            'body' => 'x',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('body');
    }

    public function test_comment_is_pending_until_moderated(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $articleId = $this->articleId($company);

        $comment = $this->postJson("/api/v1/travel/community/articles/{$articleId}/comments", [
            'body' => 'Très bonne nouvelle ligne !',
        ])->assertStatus(201)->json('data');

        $this->assertSame('pending', $comment['status']);

        $this->postJson("/api/v1/travel/community/comments/{$comment['id']}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_report_is_traced_and_unique(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $articleId = $this->articleId($company);

        $comment = $this->postJson("/api/v1/travel/community/articles/{$articleId}/comments", [
            'body' => 'Contenu à signaler pour modération.',
        ])->assertStatus(201)->json('data');

        $this->postJson("/api/v1/travel/community/comments/{$comment['id']}/report", [
            'reason' => 'Propos inappropriés',
        ])->assertOk()
            ->assertJsonPath('data.status', 'reported')
            ->assertJsonPath('data.report_reason', 'Propos inappropriés');

        // Un second signalement est refusé (422) — tracé unique.
        $this->postJson("/api/v1/travel/community/comments/{$comment['id']}/report", [
            'reason' => 'Double signalement',
        ])->assertStatus(422);
    }

    public function test_comment_of_another_tenant_is_invisible(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($companyA);
        $this->principal($companyA);

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $commentId = app(TenantManager::class)->withinTenant($companyB, function (): int {
            $article = TravelArticle::factory()->create();

            return TravelComment::factory()->create(['article_id' => $article->id])->id;
        });

        $this->postJson("/api/v1/travel/community/comments/{$commentId}/approve")->assertStatus(404);
    }
}
