<?php

declare(strict_types=1);

namespace Tests\Feature\SearchIntelligence;

use App\Core\Tenant\Domain\Enums\PlatformRole;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\SearchIntelligence\Domain\Enums\ContentStatus;
use App\Modules\SearchIntelligence\Domain\Models\ContentItem;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * GSI-6 (#8517) — contenu marketing : publication conditionnée à l'approbation
 * ET à une URL, transitions contrôlées, filtres et export CSV.
 */
final class ContentItemTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_publish_without_url_returns_422(): void
    {
        $item = $this->makeItem(ContentStatus::Draft);
        $item->forceFill([
            'status' => ContentStatus::Approved->value,
            'approved_at' => now(),
        ])->save();

        $this->actingAsMarketing();

        // Sans URL cible, publier revient à publier nulle part.
        $this->postJson("/api/v1/platform/search-intel/content/{$item->id}/transition", [
            'status' => 'published',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'publish_requires_url');
    }

    public function test_publish_with_url_succeeds(): void
    {
        $item = $this->makeItem(ContentStatus::Draft);
        $item->forceFill([
            'status' => ContentStatus::Approved->value,
            'approved_at' => now(),
        ])->save();

        $this->actingAsMarketing();

        $this->postJson("/api/v1/platform/search-intel/content/{$item->id}/transition", [
            'status' => 'published',
            'target_url' => 'https://leopardo-rh.com/blog/paie-restaurant',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $item->refresh();
        self::assertNotNull($item->published_at);
        self::assertNotNull($item->approved_at);
    }

    public function test_idea_cannot_jump_to_published(): void
    {
        $item = $this->makeItem(ContentStatus::Idea);
        $this->actingAsMarketing();
        $this->postJson("/api/v1/platform/search-intel/content/{$item->id}/transition", [
            'status' => 'published',
            'target_url' => 'https://leopardo-rh.com/x',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_transition');
    }

    public function test_full_transition_chain_is_required(): void
    {
        $item = $this->makeItem(ContentStatus::Idea);
        $this->actingAsMarketing();

        foreach (['draft', 'review', 'approved'] as $step) {
            $this->postJson("/api/v1/platform/search-intel/content/{$item->id}/transition", ['status' => $step])
                ->assertOk()
                ->assertJsonPath('data.status', $step);
        }

        self::assertSame(ContentStatus::Approved, $item->refresh()->status);
    }

    public function test_published_item_cannot_be_edited_in_place(): void
    {
        $item = $this->makeItem(ContentStatus::Draft);
        // Le statut et `approved_at`/`published_at` ne sont pas
        // mass-assignables : ils résultent d'une transition auditée, pas d'un
        // `create()`.
        $item->forceFill([
            'status' => ContentStatus::Published->value,
            'target_url' => 'https://leopardo-rh.com/x',
            'approved_at' => now(),
            'published_at' => now(),
        ])->save();

        $this->actingAsMarketing();

        $this->patchJson("/api/v1/platform/search-intel/content/{$item->id}", ['title' => 'Nouveau titre'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'content_not_editable');
    }

    public function test_to_update_removes_from_public_without_deleting(): void
    {
        $item = $this->makeItem(ContentStatus::Draft);
        $item->forceFill([
            'status' => ContentStatus::Published->value,
            'target_url' => 'https://leopardo-rh.com/x',
            'approved_at' => now(),
            'published_at' => now(),
        ])->save();

        $this->actingAsMarketing();

        $this->postJson("/api/v1/platform/search-intel/content/{$item->id}/transition", ['status' => 'to_update'])
            ->assertOk()
            ->assertJsonPath('data.status', 'to_update');

        // Le contenu existe toujours : il est seulement sorti du public.
        self::assertNotNull(ContentItem::query()->find($item->id));
    }

    public function test_filters_by_status_kind_sector_and_locale(): void
    {
        $this->actingAsMarketing();

        $this->postJson('/api/v1/platform/search-intel/content', [
            'title' => 'Guide paie',
            'kind' => 'guide',
            'locale' => 'fr',
            'sector' => 'restaurant',
        ])->assertStatus(201);

        $this->postJson('/api/v1/platform/search-intel/content', [
            'title' => 'Landing pharmacie',
            'kind' => 'landing',
            'locale' => 'fr',
            'sector' => 'pharmacie',
        ])->assertStatus(201);

        self::assertCount(1, $this->getJson('/api/v1/platform/search-intel/content?kind=guide')->json('data'));
        self::assertCount(1, $this->getJson('/api/v1/platform/search-intel/content?sector=pharmacie')->json('data'));
        self::assertCount(2, $this->getJson('/api/v1/platform/search-intel/content?locale=fr')->json('data'));
        self::assertCount(2, $this->getJson('/api/v1/platform/search-intel/content?status=idea')->json('data'));
    }

    public function test_csv_export_contains_the_header_and_rows(): void
    {
        $this->makeItem(ContentStatus::Draft, ['title' => 'Article à exporter']);

        $this->actingAsMarketing();

        $response = $this->get('/api/v1/platform/search-intel/content/export')->assertOk();

        self::assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));

        $body = $response->streamedContent();
        self::assertStringContainsString('id,title,kind,status,locale,sector,target_url,published_at', $body);
        self::assertStringContainsString('Article à exporter', $body);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeItem(ContentStatus $status, array $attributes = []): ContentItem
    {
        return ContentItem::query()->create(array_merge([
            'title' => 'Contenu '.uniqid(),
            'kind' => 'blog',
            'status' => $status->value,
            'locale' => 'fr',
        ], $attributes));
    }

    private function actingAsMarketing(): void
    {
        $admin = new SuperAdmin([
            'name' => 'Marketing',
            'email' => 'marketing-'.uniqid().'@leopardo.test',
        ]);

        $admin->forceFill([
            'password_hash' => Hash::make('x'),
            'platform_role' => PlatformRole::Marketing->value,
        ])->save();

        Sanctum::actingAs($admin->refresh(), ['*'], 'super_admin_api');
    }
}
