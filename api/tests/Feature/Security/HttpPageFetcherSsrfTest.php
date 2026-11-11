<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Modules\SearchIntelligence\Infrastructure\Services\HttpPageFetcher;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * #9348 (audit sécurité 2026-11-10, F6) — garde anti-SSRF du fetcher de pages.
 *
 * Le fetcher était le seul point de sortie du dépôt à ne pas passer par
 * `NotPrivateUrl`, et Guzzle suivait les redirections tout seul — donc une
 * `302 → http://10.0.0.1/` contournait un contrôle portant sur l'URL initiale.
 */
class HttpPageFetcherSsrfTest extends TestCase
{
    public function test_it_refuses_a_loopback_target_without_emitting_a_request(): void
    {
        Http::fake();

        $fetcher = new HttpPageFetcher;

        $this->assertNull($fetcher->fetch('https://127.0.0.1/admin'));
        Http::assertNothingSent();
    }

    public function test_it_refuses_the_cloud_metadata_endpoint(): void
    {
        Http::fake();

        $fetcher = new HttpPageFetcher;

        $this->assertNull($fetcher->fetch('https://169.254.169.254/latest/meta-data/'));
        Http::assertNothingSent();
    }

    public function test_it_refuses_a_private_rfc1918_target(): void
    {
        Http::fake();

        $fetcher = new HttpPageFetcher;

        $this->assertNull($fetcher->fetch('https://10.0.0.1/'));
        $this->assertNull($fetcher->fetch('https://192.168.1.10/'));
        Http::assertNothingSent();
    }

    public function test_it_refuses_a_non_https_scheme(): void
    {
        Http::fake();

        $fetcher = new HttpPageFetcher;

        $this->assertNull($fetcher->fetch('http://example.com/'));
        Http::assertNothingSent();
    }

    public function test_it_does_not_follow_a_redirect_towards_a_private_host(): void
    {
        // Le cas qui rendait la garde contournable : l'URL initiale est
        // publique, la redirection pointe vers le réseau interne.
        Http::fake([
            'https://public.example/start' => Http::response('', 302, [
                'Location' => 'https://10.0.0.1/secret',
            ]),
        ]);

        $fetcher = new HttpPageFetcher;

        $this->assertNull($fetcher->fetch('https://public.example/start'));

        // La requête initiale a bien été émise, mais AUCUNE vers 10.0.0.1.
        Http::assertSent(static fn ($request): bool => $request->url() === 'https://public.example/start');
        Http::assertNotSent(static fn ($request): bool => str_contains($request->url(), '10.0.0.1'));
    }

    public function test_it_follows_a_redirect_between_public_hosts(): void
    {
        Http::fake([
            'https://public.example/start' => Http::response('', 301, [
                'Location' => '/final',
            ]),
            'https://public.example/final' => Http::response('<html>ok</html>', 200),
        ]);

        $fetcher = new HttpPageFetcher;
        $result = $fetcher->fetch('https://public.example/start');

        $this->assertIsArray($result);
        $this->assertSame(200, $result['status']);
        $this->assertSame('<html>ok</html>', $result['body']);
    }
}
