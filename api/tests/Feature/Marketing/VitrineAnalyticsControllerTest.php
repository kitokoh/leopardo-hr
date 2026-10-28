<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #7595 — ingestion des pages vues de la vitrine (analytique first-party).
 *
 * Couvre les deux règles qui font la valeur de l'issue :
 *
 * 1. **Consentement** : sans accord explicite, rien n'est écrit — et la
 *    réponse est un 202, pas une erreur, pour que la vitrine ne réessaie pas.
 * 2. **Agrégation** : deux vues de la même page le même jour incrémentent un
 *    compteur, elles ne créent pas deux lignes.
 *
 * Et les normalisations qui protègent la cardinalité et la vie privée :
 * query string retirée du chemin, referrer interne ignoré, referrer externe
 * réduit à son hôte.
 */
class VitrineAnalyticsControllerTest extends TestCase
{
    use RefreshTenantDatabase;

    private const SECRET = 'vitrine-shared-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.marketing_lead_webhook.secret', self::SECRET);
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @return array<string, mixed>
     */
    private function payload(array $events, bool $consent = true): array
    {
        return ['consent' => $consent, 'events' => $events];
    }

    private function sendPayload(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/vitrine/analytics/page-views', $payload, [
            'Authorization' => 'Bearer '.self::SECRET,
        ]);
    }

    public function test_it_stores_a_page_view_with_the_shared_secret(): void
    {
        $response = $this->sendPayload($this->payload([
            ['path' => '/pricing', 'source' => 'google', 'utm_medium' => 'cpc'],
        ]));

        $response->assertStatus(202)->assertJson(['received' => true, 'stored' => 1, 'consent' => true]);

        $this->assertDatabaseHas('vitrine_page_views', [
            'page_path' => '/pricing',
            'source' => 'google',
            'utm_medium' => 'cpc',
            'views' => 1,
        ]);
    }

    public function test_no_row_is_written_without_consent(): void
    {
        $response = $this->sendPayload($this->payload([
            ['path' => '/pricing'],
        ], consent: false));

        // 202 et non 4xx : un refus utilisateur n'est pas une erreur d'appelant.
        $response->assertStatus(202)->assertJson(['stored' => 0, 'consent' => false]);

        $this->assertSame(0, DB::table('vitrine_page_views')->count());
    }

    public function test_repeated_views_increments_the_daily_counter(): void
    {
        $this->sendPayload($this->payload([['path' => '/pricing']]))->assertStatus(202);
        $this->sendPayload($this->payload([['path' => '/pricing']]))->assertStatus(202);
        $this->sendPayload($this->payload([['path' => '/pricing/']]))->assertStatus(202);

        $rows = DB::table('vitrine_page_views')->get();

        // Une seule ligne : `/pricing` et `/pricing/` sont la même page.
        $this->assertCount(1, $rows);
        $this->assertSame(3, (int) $rows->first()->views);
    }

    public function test_query_string_is_stripped_from_the_stored_path(): void
    {
        $this->sendPayload($this->payload([
            ['path' => '/pricing?utm_source=facebook&email=someone@example.com'],
        ]))->assertStatus(202);

        $stored = DB::table('vitrine_page_views')->first();

        $this->assertNotNull($stored);
        $this->assertSame('/pricing', $stored->page_path);
    }

    public function test_external_referrer_is_reduced_to_its_host_and_internal_one_is_ignored(): void
    {
        config()->set('services.vitrine.host', 'leopardo-rh.com');

        $this->sendPayload($this->payload([
            ['path' => '/pricing', 'referrer' => 'https://news.example.com/article?q=secret-search'],
        ]))->assertStatus(202);

        $this->sendPayload($this->payload([
            ['path' => '/pricing', 'referrer' => 'https://leopardo-rh.com/'],
        ]))->assertStatus(202);

        $sources = DB::table('vitrine_page_views')->pluck('source')->all();

        $this->assertContains('news.example.com', $sources);
        // Referrer interne : pas de source parasite, donc NULL.
        $this->assertContains(null, $sources);
        // La requête de recherche du referrer ne doit jamais être stockée.
        $this->assertStringNotContainsString('secret-search', (string) json_encode($sources));
    }

    public function test_invalid_secret_is_rejected_when_configured(): void
    {
        $this->postJson('/api/v1/vitrine/analytics/page-views', $this->payload([
            ['path' => '/pricing'],
        ]), ['Authorization' => 'Bearer wrong-secret'])->assertStatus(400);

        $this->assertSame(0, DB::table('vitrine_page_views')->count());
    }

    public function test_consent_flag_is_required(): void
    {
        $this->postJson('/api/v1/vitrine/analytics/page-views', [
            'events' => [['path' => '/pricing']],
        ], ['Authorization' => 'Bearer '.self::SECRET])->assertStatus(422);

        $this->assertSame(0, DB::table('vitrine_page_views')->count());
    }
}
