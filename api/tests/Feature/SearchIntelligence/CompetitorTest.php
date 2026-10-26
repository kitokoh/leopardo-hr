<?php

declare(strict_types=1);

namespace Tests\Feature\SearchIntelligence;

use App\Core\Tenant\Domain\Enums\PlatformRole;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\SearchIntelligence\Domain\Models\Competitor;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * GSI-7 (#8518) — veille concurrentielle : toute affirmation tarifaire exige
 * une source datée, et la revue ne rajeunit pas l'observation.
 */
final class CompetitorTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_pricing_notes_without_sources_return_422(): void
    {
        $this->actingAsMarketing();

        $this->postJson('/api/v1/platform/search-intel/competitors', [
            'name' => 'Concurrent SA',
            'pricing_notes' => 'Abonnement à 49 €/mois.',
            'source_url' => 'https://concurrent.example.com',
            'source_label' => 'Site officiel',
            'observed_at' => '2026-10-01T10:00:00+00:00',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'pricing_requires_sources');
    }

    public function test_pricing_source_missing_observed_at_returns_422(): void
    {
        $this->actingAsMarketing();

        // Une URL sans date n'est pas une source datée : le prix reste
        // invérifiable.
        $this->postJson('/api/v1/platform/search-intel/competitors', [
            'name' => 'Concurrent SA',
            'pricing_notes' => 'Abonnement à 49 €/mois.',
            'pricing_sources' => [
                ['url' => 'https://concurrent.example.com/tarifs', 'label' => 'Page tarifs'],
            ],
            'source_url' => 'https://concurrent.example.com',
            'source_label' => 'Site officiel',
            'observed_at' => '2026-10-01T10:00:00+00:00',
        ])->assertStatus(422);
    }

    public function test_pricing_with_dated_source_is_accepted(): void
    {
        $this->actingAsMarketing();

        $this->postJson('/api/v1/platform/search-intel/competitors', [
            'name' => 'Concurrent SA',
            'pricing_notes' => 'Abonnement à 49 €/mois.',
            'pricing_sources' => [
                [
                    'url' => 'https://concurrent.example.com/tarifs',
                    'label' => 'Page tarifs',
                    'observed_at' => '2026-10-01T10:00:00+00:00',
                ],
            ],
            'source_url' => 'https://concurrent.example.com',
            'source_label' => 'Site officiel',
            'observed_at' => '2026-10-01T10:00:00+00:00',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Concurrent SA');
    }

    public function test_competitor_without_pricing_needs_no_pricing_source(): void
    {
        $this->actingAsMarketing();

        $this->postJson('/api/v1/platform/search-intel/competitors', [
            'name' => 'Concurrent sans prix public',
            'positioning' => 'Haut de gamme',
            'source_url' => 'https://example.com',
            'source_label' => 'Presse spécialisée',
            'observed_at' => '2026-10-01T10:00:00+00:00',
        ])->assertStatus(201);
    }

    public function test_review_updates_last_reviewed_at_but_not_observed_at(): void
    {
        $competitor = $this->makeCompetitor();

        $originalObserved = $competitor->observed_at->toIso8601String();
        self::assertNull($competitor->last_reviewed_at);

        $this->actingAsMarketing();

        $this->postJson("/api/v1/platform/search-intel/competitors/{$competitor->id}/review")
            ->assertOk();

        $competitor->refresh();

        // Relire une veille n'est pas la refaire : `observed_at` ne bouge pas.
        self::assertNotNull($competitor->last_reviewed_at);
        self::assertSame($originalObserved, $competitor->observed_at->toIso8601String());
    }

    public function test_stale_observation_is_flagged(): void
    {
        $competitor = $this->makeCompetitor(['observed_at' => now()->subDays(200)]);

        $this->actingAsMarketing();

        $response = $this->getJson('/api/v1/platform/search-intel/competitors')->assertOk();

        self::assertTrue($response->json('data.0.is_stale'));
        self::assertNotNull($response->json('meta.stale_before'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeCompetitor(array $attributes = []): Competitor
    {
        return Competitor::query()->create(array_merge([
            'name' => 'Concurrent '.uniqid(),
            'market' => 'restaurant',
            'positioning' => 'Milieu de gamme',
            'value_proposition' => 'Paie intégrée',
            'source_url' => 'https://example.com',
            'source_label' => 'Site officiel',
            'observed_at' => now(),
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
