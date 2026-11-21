<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantReview;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #9713 — Modération back-office des avis : volet gestion complet.
 *
 * Couvre ce que le parcours client ne couvrait pas : filtres de la file
 * (statut + note), retrait d'un avis publié (`unpublish`), réponse publique
 * du restaurateur (`reply` / `deleteReply`), exposition de la réponse sur le
 * canal public, RBAC (403 employé) et isolation cross-tenant (404).
 */
class RestaurantReviewModerationTest extends TestCase
{
    use RefreshTenantDatabase;

    private function makeTenant(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('restaurantmanager', true);
        $company->save();

        return $company;
    }

    private function makeBranch(Company $company, string $slug = 'chez-leo'): RestaurantBranch
    {
        return app(TenantManager::class)->withinTenant(
            $company,
            fn (): RestaurantBranch => RestaurantBranch::factory()->create([
                'is_public' => true,
                'status' => 'active',
                'currency' => 'XAF',
                'public_slug' => $slug,
            ])
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeReview(Company $company, RestaurantBranch $branch, array $attributes = []): RestaurantReview
    {
        return app(TenantManager::class)->withinTenant(
            $company,
            fn (): RestaurantReview => RestaurantReview::query()->create(array_merge([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'order_reference' => 'RST-MOD'.random_int(100000, 999999),
                'rating' => 4,
                'author_name' => 'Awa',
                'status' => 'pending',
            ], $attributes))
        );
    }

    private function actAsPrincipal(Company $company): Employee
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

    public function test_index_filters_by_status_and_rating(): void
    {
        $company = $this->makeTenant();
        $branch = $this->makeBranch($company);

        $this->makeReview($company, $branch, ['status' => 'pending', 'rating' => 5]);
        $this->makeReview($company, $branch, ['status' => 'pending', 'rating' => 2]);
        $this->makeReview($company, $branch, ['status' => 'published', 'rating' => 5]);

        $this->actAsPrincipal($company);

        $this->getJson('/api/v1/restaurant/reviews?status=pending')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/restaurant/reviews?status=pending&rating=5')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.rating', 5);

        $this->getJson('/api/v1/restaurant/reviews?status=published')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_unpublish_returns_review_to_pending_and_hides_it_publicly(): void
    {
        $company = $this->makeTenant();
        $branch = $this->makeBranch($company);
        $review = $this->makeReview($company, $branch, ['status' => 'published']);

        $this->getJson('/api/v1/public/restaurants/chez-leo/reviews')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actAsPrincipal($company);

        $this->postJson('/api/v1/restaurant/reviews/'.$review->id.'/unpublish')
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        $this->getJson('/api/v1/public/restaurants/chez-leo/reviews')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_manager_replies_to_review_and_reply_is_public(): void
    {
        $company = $this->makeTenant();
        $branch = $this->makeBranch($company);
        $review = $this->makeReview($company, $branch, ['status' => 'published']);

        $this->actAsPrincipal($company);

        $this->putJson('/api/v1/restaurant/reviews/'.$review->id.'/reply', [
            'reply' => 'Merci Awa, au plaisir de vous revoir !',
        ])
            ->assertOk()
            ->assertJsonPath('data.reply', 'Merci Awa, au plaisir de vous revoir !')
            ->assertJsonPath('data.replied_at', fn ($v): bool => is_string($v));

        // La réponse est exposée sur le canal public, sans identité d'employé.
        $response = $this->getJson('/api/v1/public/restaurants/chez-leo/reviews')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reply', 'Merci Awa, au plaisir de vous revoir !');

        $item = (array) $response->json('data.0');
        $this->assertArrayNotHasKey('replied_by', $item);
        $this->assertArrayNotHasKey('order_reference', $item);
    }

    public function test_reply_is_validated_and_can_be_deleted(): void
    {
        $company = $this->makeTenant();
        $branch = $this->makeBranch($company);
        $review = $this->makeReview($company, $branch, ['status' => 'published']);

        $this->actAsPrincipal($company);

        // Réponse vide → 422.
        $this->putJson('/api/v1/restaurant/reviews/'.$review->id.'/reply', ['reply' => ''])
            ->assertStatus(422);

        $this->putJson('/api/v1/restaurant/reviews/'.$review->id.'/reply', ['reply' => 'Merci !'])
            ->assertOk();

        $this->deleteJson('/api/v1/restaurant/reviews/'.$review->id.'/reply')
            ->assertOk()
            ->assertJsonPath('data.reply', null)
            ->assertJsonPath('data.replied_at', null);

        $this->getJson('/api/v1/public/restaurants/chez-leo/reviews')
            ->assertOk()
            ->assertJsonPath('data.0.reply', null);
    }

    public function test_reply_requires_manager_and_is_tenant_scoped(): void
    {
        $company = $this->makeTenant();
        $branch = $this->makeBranch($company);
        $review = $this->makeReview($company, $branch, ['status' => 'published']);

        // Employé sans rôle gérant → 403.
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'manager_role' => null,
        ]);
        Sanctum::actingAs($employee);

        $this->putJson('/api/v1/restaurant/reviews/'.$review->id.'/reply', ['reply' => 'Merci'])
            ->assertStatus(403);

        // Gérant d'un AUTRE tenant → 404 (existence non révélée).
        $other = $this->makeTenant();
        $this->actAsPrincipal($other);

        $this->putJson('/api/v1/restaurant/reviews/'.$review->id.'/reply', ['reply' => 'Merci'])
            ->assertNotFound();

        $this->deleteJson('/api/v1/restaurant/reviews/'.$review->id.'/reply')
            ->assertNotFound();
    }

    public function test_rejected_review_never_exposes_its_reply(): void
    {
        $company = $this->makeTenant();
        $branch = $this->makeBranch($company);
        $review = $this->makeReview($company, $branch, ['status' => 'published']);

        $this->actAsPrincipal($company);

        $this->putJson('/api/v1/restaurant/reviews/'.$review->id.'/reply', ['reply' => 'Merci !'])
            ->assertOk();

        $this->postJson('/api/v1/restaurant/reviews/'.$review->id.'/reject')
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        // Avis rejeté → invisible publiquement, donc sa réponse aussi.
        $this->getJson('/api/v1/public/restaurants/chez-leo/reviews')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
