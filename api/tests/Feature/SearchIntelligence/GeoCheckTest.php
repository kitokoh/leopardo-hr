<?php

declare(strict_types=1);

namespace Tests\Feature\SearchIntelligence;

use App\Core\Tenant\Domain\Enums\PlatformRole;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\SearchIntelligence\Domain\Enums\GeoCheckMethod;
use App\Modules\SearchIntelligence\Domain\Models\GeoCheck;
use App\Modules\SearchIntelligence\Domain\Models\GeoQuery;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * GSI-5 (#8516) — checks GEO : méthode obligatoire, historique ordonné et
 * immuable, badge de méthode dans la réponse.
 */
final class GeoCheckTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_invalid_method_returns_422(): void
    {
        $query = $this->makeQuery();
        $this->actingAsMarketing();

        $this->postJson("/api/v1/platform/search-intel/geo/queries/{$query->id}/checks", [
            'method' => 'vibes',
            'engine' => 'chatgpt',
            'is_present' => true,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('method');
    }

    public function test_missing_method_returns_422(): void
    {
        $query = $this->makeQuery();
        $this->actingAsMarketing();

        $this->postJson("/api/v1/platform/search-intel/geo/queries/{$query->id}/checks", [
            'engine' => 'chatgpt',
            'is_present' => true,
        ])->assertStatus(422)->assertJsonValidationErrors('method');
    }

    public function test_spot_test_without_trace_is_refused(): void
    {
        $query = $this->makeQuery();
        $this->actingAsMarketing();

        // Un test ponctuel sans trace textuelle est une affirmation
        // invérifiable : refusé avant l'écriture.
        $this->postJson("/api/v1/platform/search-intel/geo/queries/{$query->id}/checks", [
            'method' => 'spot_test',
            'engine' => 'chatgpt',
            'is_present' => true,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'evidence_required');
    }

    public function test_history_is_ordered_newest_first(): void
    {
        $query = $this->makeQuery();
        $this->actingAsMarketing();

        foreach ([
            ['observed_at' => '2026-09-01T10:00:00+00:00', 'text' => 'Premier'],
            ['observed_at' => '2026-10-01T10:00:00+00:00', 'text' => 'Deuxième'],
            ['observed_at' => '2026-09-15T10:00:00+00:00', 'text' => 'Entre les deux'],
        ] as $entry) {
            $this->postJson("/api/v1/platform/search-intel/geo/queries/{$query->id}/checks", [
                'method' => 'observed_data',
                'engine' => 'chatgpt',
                'is_present' => true,
                'observed_at' => $entry['observed_at'],
                'observed_text' => $entry['text'],
            ])->assertStatus(201);
        }

        $history = $this->getJson("/api/v1/platform/search-intel/geo/queries/{$query->id}/checks")->assertOk();

        self::assertCount(3, $history->json('data'));
        // Ordre décroissant strict sur observed_at.
        self::assertSame('Deuxième', $history->json('data.0.observed_text'));
        self::assertSame('Entre les deux', $history->json('data.1.observed_text'));
        self::assertSame('Premier', $history->json('data.2.observed_text'));
    }

    public function test_response_carries_the_method_badge(): void
    {
        $query = $this->makeQuery();
        $this->actingAsMarketing();

        $response = $this->postJson("/api/v1/platform/search-intel/geo/queries/{$query->id}/checks", [
            'method' => 'measured_indicator',
            'engine' => 'perplexity',
            'is_present' => true,
            'position' => 2,
        ])->assertStatus(201);

        // Le niveau de preuve VOYAGE avec la donnée : le front ne peut pas
        // afficher un test ponctuel comme un indicateur mesuré.
        $response->assertJsonPath('data.method', 'measured_indicator')
            ->assertJsonPath('data.method_badge', 'Indicateur mesuré')
            ->assertJsonPath('data.supports_quantified_claim', true);
    }

    public function test_spot_test_does_not_support_quantified_claim(): void
    {
        $query = $this->makeQuery();
        $this->actingAsMarketing();

        $this->postJson("/api/v1/platform/search-intel/geo/queries/{$query->id}/checks", [
            'method' => 'spot_test',
            'engine' => 'gemini',
            'is_present' => true,
            'observed_text' => 'La marque apparaît en 3e position.',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.supports_quantified_claim', false)
            ->assertJsonPath('data.method_badge', 'Test ponctuel');
    }

    public function test_check_is_immutable_in_the_model(): void
    {
        $query = $this->makeQuery();

        $check = GeoCheck::query()->create([
            'geo_query_id' => $query->id,
            'engine' => 'chatgpt',
            'method' => GeoCheckMethod::ObservedData->value,
            'is_present' => true,
            'observed_text' => 'Observation initiale.',
            'observed_at' => now(),
        ]);

        $this->expectException(\LogicException::class);

        // Réécrire une observation passée est interdit : on ajoute.
        $check->update(['observed_text' => 'Réécriture du passé.']);
    }

    public function test_weakest_evidence_plateaus_the_query(): void
    {
        $query = $this->makeQuery();

        GeoCheck::query()->create([
            'geo_query_id' => $query->id,
            'engine' => 'chatgpt',
            'method' => GeoCheckMethod::MeasuredIndicator->value,
            'is_present' => true,
            'observed_at' => now(),
        ]);

        GeoCheck::query()->create([
            'geo_query_id' => $query->id,
            'engine' => 'gemini',
            'method' => GeoCheckMethod::SpotTest->value,
            'is_present' => true,
            'observed_text' => 'Un essai.',
            'observed_at' => now(),
        ]);

        // Un seul test ponctuel suffit à plafonner ce qu'on peut affirmer.
        self::assertSame(GeoCheckMethod::SpotTest, $query->weakestEvidence());
    }

    private function makeQuery(): GeoQuery
    {
        return GeoQuery::query()->create([
            'query' => 'meilleur logiciel de paie restaurant',
            'locale' => 'fr',
            'priority' => 1,
            'is_active' => true,
        ]);
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
