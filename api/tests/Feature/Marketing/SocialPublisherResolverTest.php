<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Modules\Marketing\Infrastructure\Services\AyrshareClient;
use App\Modules\Marketing\Infrastructure\Services\Publishers\LinkedInPublisher;
use App\Modules\Marketing\Infrastructure\Services\Publishers\MetaPublisher;
use App\Modules\Marketing\Infrastructure\Services\Publishers\SocialPublisherResolver;
use App\Modules\Marketing\Infrastructure\Services\Publishers\TwitterPublisher;
use RuntimeException;
use Tests\TestCase;

/**
 * Issue #1433 — SocialPublisherInterface + LinkedIn/Meta/Twitter publishers.
 */
class SocialPublisherResolverTest extends TestCase
{
    private function makeResolver(): SocialPublisherResolver
    {
        $ayrshareClient = app(AyrshareClient::class);

        return new SocialPublisherResolver([
            new LinkedInPublisher($ayrshareClient),
            new MetaPublisher($ayrshareClient),
            new TwitterPublisher($ayrshareClient),
        ]);
    }

    public function test_resolves_linkedin_platform_to_linkedin_publisher(): void
    {
        $publisher = $this->makeResolver()->resolve('linkedin');

        $this->assertInstanceOf(LinkedInPublisher::class, $publisher);
    }

    public function test_resolves_meta_platforms_to_meta_publisher(): void
    {
        $resolver = $this->makeResolver();

        foreach (['facebook_page', 'facebook_group', 'instagram', 'threads'] as $platform) {
            $this->assertInstanceOf(MetaPublisher::class, $resolver->resolve($platform));
        }
    }

    public function test_resolves_twitter_platform_to_twitter_publisher(): void
    {
        $publisher = $this->makeResolver()->resolve('twitter');

        $this->assertInstanceOf(TwitterPublisher::class, $publisher);
    }

    public function test_resolve_throws_for_unknown_platform(): void
    {
        $this->expectException(RuntimeException::class);

        $this->makeResolver()->resolve('tiktok');
    }

    public function test_group_by_publisher_groups_mixed_platforms(): void
    {
        $resolver = $this->makeResolver();

        $groups = $resolver->groupByPublisher(['linkedin', 'facebook_page', 'twitter', 'instagram']);

        $this->assertCount(3, $groups);

        $platformsByPublisherClass = [];
        foreach ($groups as $group) {
            $platformsByPublisherClass[$group['publisher']::class] = $group['platforms'];
        }

        $this->assertSame(['linkedin'], $platformsByPublisherClass[LinkedInPublisher::class]);
        $this->assertSame(['facebook_page', 'instagram'], $platformsByPublisherClass[MetaPublisher::class]);
        $this->assertSame(['twitter'], $platformsByPublisherClass[TwitterPublisher::class]);
    }
}
