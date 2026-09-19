<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantReview;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-902 (#7747) — Avis clients : soumission publique par slug, lecture
 * publique (publiés uniquement) et modération tenant (publish/reject).
 *
 * Couvre : création liée à une commande SERVIE de la branche (pending),
 * commande non terminale refusée (422), référence inconnue → 404, double
 * avis refusé (409), GET public n'expose que les avis publiés (DTO strict
 * sans order_reference), modération publish/reject (gérant, 403 employé,
 * 404 cross-tenant) et note moyenne sur l'annuaire + le profil public.
 */
class RestaurantReviewTest extends TestCase
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makePublicBranch(Company $company, array $attributes = []): RestaurantBranch
    {
        return app(TenantManager::class)->withinTenant(
            $company,
            fn (): RestaurantBranch => RestaurantBranch::factory()->create(array_merge([
                'is_public' => true,
                'status' => 'active',
                'currency' => 'XAF',
            ], $attributes))
        );
    }

    private function makeOrder(Company $company, RestaurantBranch $branch, string $status = 'served'): RestaurantOrder
    {
        return app(TenantManager::class)->withinTenant(
            $company,
            fn (): RestaurantOrder => RestaurantOrder::query()->create([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'order_type' => 'takeaway',
                'status' => $status,
                'currency' => 'XAF',
                'source' => 'online',
                'total_minor' => 5000,
            ])
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

    public function test_creates_pending_review_for_served_order(): void
    {
        $company = $this->makeTenant();
        $branch = $this->makePublicBranch($company, ['public_slug' => 'chez-leo']);
        $order = $this->makeOrder($company, $branch, 'served');

        $this->postJson('/api/v1/public/restaurants/chez-leo/reviews', [
            'order_ref' => $order->reference,
            'rating' => 5,
            'comment' => 'Excellent poulet DG.',
            'author_name' => 'Awa',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.rating', 5);

        // Un avis pending n'est PAS public.
        $this->getJson('/api/v1/public/restaurants/chez-leo/reviews')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_rejects_review_for_non_terminal_order(): void
    {
        $company = $this->makeTenant();
        $branch = $this->makePublicBranch($company, ['public_slug' => 'chez-leo']);
        $order = $this->makeOrder($company, $branch, 'in_preparation');

        $this->postJson('/api/v1/public/restaurants/chez-leo/reviews', [
            'order_ref' => $order->reference,
            'rating' => 4,
            'author_name' => 'Awa',
        ])->assertStatus(422);
    }

    public function test_rejects_review_with_unknown_or_foreign_branch_reference(): void
    {
        $company = $this->makeTenant();
        $this->makePublicBranch($company, ['public_slug' => 'chez-leo']);
        $other = $this->makePublicBranch($company, ['public_slug' => 'chez-ali']);
        $orderOfOtherBranch = $this->makeOrder($company, $other, 'served');

        // Référence inconnue → 404 fail-closed.
        $this->postJson('/api/v1/public/restaurants/chez-leo/reviews', [
            'order_ref' => 'RST-INCONNUE99',
            'rating' => 5,
            'author_name' => 'Awa',
        ])->assertNotFound();

        // Commande d'une AUTRE branche → 404 (l'avis est lié à LA branche).
        $this->postJson('/api/v1/public/restaurants/chez-leo/reviews', [
            'order_ref' => $orderOfOtherBranch->reference,
            'rating' => 5,
            'author_name' => 'Awa',
        ])->assertNotFound();
    }

    public function test_rejects_second_review_for_the_same_order(): void
    {
        $company = $this->makeTenant();
        $branch = $this->makePublicBranch($company, ['public_slug' => 'chez-leo']);
        $order = $this->makeOrder($company, $branch, 'served');

        $payload = [
            'order_ref' => $order->reference,
            'rating' => 5,
            'author_name' => 'Awa',
        ];

        $this->postJson('/api/v1/public/restaurants/chez-leo/reviews', $payload)->assertCreated();
        $this->postJson('/api/v1/public/restaurants/chez-leo/reviews', $payload)->assertStatus(409);
    }

    public function test_public_listing_only_shows_published_reviews_with_strict_dto(): void
    {
        $company = $this->makeTenant();
        $branch = $this->makePublicBranch($company, ['public_slug' => 'chez-leo']);

        app(TenantManager::class)->withinTenant($company, function () use ($company, $branch): void {
            foreach ([['pending', 3], ['published', 5], ['rejected', 1]] as $i => [$status, $rating]) {
                RestaurantReview::query()->create([
                    'company_id' => $company->id,
                    'branch_id' => $branch->id,
                    'order_reference' => 'RST-TEST000'.$i,
                    'rating' => $rating,
                    'comment' => 'Avis '.$status,
                    'author_name' => 'Client '.$i,
                    'status' => $status,
                ]);
            }
        });

        $response = $this->getJson('/api/v1/public/restaurants/chez-leo/reviews')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.rating', 5)
            ->assertJsonPath('data.0.comment', 'Avis published');

        // DTO public STRICT : ni order_reference, ni id, ni statut interne.
        $item = (array) $response->json('data.0');
        $this->assertArrayNotHasKey('order_reference', $item);
        $this->assertArrayNotHasKey('id', $item);
        $this->assertArrayNotHasKey('company_id', $item);
    }

    public function test_manager_moderates_review_publish_then_reject(): void
    {
        $company = $this->makeTenant();
        $branch = $this->makePublicBranch($company, ['public_slug' => 'chez-leo']);
        $order = $this->makeOrder($company, $branch, 'served');

        $this->postJson('/api/v1/public/restaurants/chez-leo/reviews', [
            'order_ref' => $order->reference,
            'rating' => 4,
            'author_name' => 'Awa',
        ])->assertCreated();

        $this->actAsPrincipal($company);

        $reviewId = $this->getJson('/api/v1/restaurant/reviews?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'pending')
            ->json('data.0.id');

        $this->assertIsInt($reviewId);

        $this->postJson('/api/v1/restaurant/reviews/'.$reviewId.'/publish')
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        // Publié → visible publiquement.
        $this->getJson('/api/v1/public/restaurants/chez-leo/reviews')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->postJson('/api/v1/restaurant/reviews/'.$reviewId.'/reject')
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        // Rejeté → disparaît de la lecture publique.
        $this->getJson('/api/v1/public/restaurants/chez-leo/reviews')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_moderation_requires_manager_and_is_tenant_scoped(): void
    {
        $company = $this->makeTenant();
        $branch = $this->makePublicBranch($company, ['public_slug' => 'chez-leo']);

        $review = app(TenantManager::class)->withinTenant(
            $company,
            fn (): RestaurantReview => RestaurantReview::query()->create([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'order_reference' => 'RST-TESTMOD01',
                'rating' => 5,
                'author_name' => 'Awa',
                'status' => 'pending',
            ])
        );

        // Employé sans rôle gérant → 403.
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'manager_role' => null,
        ]);
        Sanctum::actingAs($employee);

        $this->postJson('/api/v1/restaurant/reviews/'.$review->id.'/publish')
            ->assertStatus(403);

        // Gérant d'un AUTRE tenant → 404 (jamais 403 : existence non révélée).
        $otherCompany = $this->makeTenant();
        $this->actAsPrincipal($otherCompany);

        $this->postJson('/api/v1/restaurant/reviews/'.$review->id.'/publish')
            ->assertNotFound();
    }

    public function test_published_reviews_feed_public_rating_average(): void
    {
        $company = $this->makeTenant();
        $branch = $this->makePublicBranch($company, [
            'public_slug' => 'chez-leo',
            'name' => 'Chez Leo',
        ]);

        app(TenantManager::class)->withinTenant($company, function () use ($company, $branch): void {
            foreach ([['published', 5], ['published', 4], ['pending', 1]] as $i => [$status, $rating]) {
                RestaurantReview::query()->create([
                    'company_id' => $company->id,
                    'branch_id' => $branch->id,
                    'order_reference' => 'RST-TESTAVG0'.$i,
                    'rating' => $rating,
                    'author_name' => 'Client '.$i,
                    'status' => $status,
                ]);
            }
        });

        // Annuaire : cartes avec rating_avg / reviews_count (publiés seuls).
        $this->getJson('/api/v1/public/restaurants')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'chez-leo')
            ->assertJsonPath('data.0.rating_avg', 4.5)
            ->assertJsonPath('data.0.reviews_count', 2);

        // Profil public : mêmes agrégats (l'avis pending ne compte pas).
        $this->getJson('/api/v1/public/restaurants/chez-leo')
            ->assertOk()
            ->assertJsonPath('data.rating_avg', 4.5)
            ->assertJsonPath('data.reviews_count', 2);
    }
}
