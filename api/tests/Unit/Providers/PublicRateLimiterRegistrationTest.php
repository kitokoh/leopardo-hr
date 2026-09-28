<?php

declare(strict_types=1);

namespace Tests\Unit\Providers;

use Tests\TestCase;

/**
 * BOS-050 (#8208) — chaque bucket de rate limiting n'est enregistré QU'UNE
 * FOIS dans les providers : un second `RateLimiter::for('<nom>')` écrase
 * silencieusement le premier (régression constatée sur `shop-public`,
 * enregistré 2× dans AppServiceProvider avant BOS-050).
 */
class PublicRateLimiterRegistrationTest extends TestCase
{
    public function test_each_rate_limiter_bucket_is_registered_exactly_once(): void
    {
        $registrations = [];

        foreach (glob(app_path('Providers/*.php')) ?: [] as $file) {
            $contents = file_get_contents($file);
            $this->assertIsString($contents);

            // Ignorer les lignes de commentaire : un exemple cité dans un
            // docblock ne doit pas compter comme un enregistrement.
            $codeLines = array_filter(
                explode("\n", $contents),
                fn (string $line): bool => ! preg_match('/^\s*(\/\/|\*|\/\*)/', $line)
            );

            preg_match_all("/RateLimiter::for\(\s*'([^']+)'/", implode("\n", $codeLines), $matches);

            foreach ($matches[1] as $bucket) {
                $registrations[$bucket][] = basename($file);
            }
        }

        $this->assertArrayHasKey('shop-public', $registrations);

        foreach ($registrations as $bucket => $files) {
            $this->assertCount(
                1,
                $files,
                sprintf('Le bucket "%s" est enregistré %d fois (%s) — la dernière définition écrase les autres.', $bucket, count($files), implode(', ', $files))
            );
        }
    }

    public function test_shop_public_bucket_resolves_to_a_limiter(): void
    {
        $limiter = \Illuminate\Support\Facades\RateLimiter::limiter('shop-public');

        $this->assertNotNull($limiter, 'Le bucket shop-public doit rester enregistré (surfaces publiques shop).');
    }
}
