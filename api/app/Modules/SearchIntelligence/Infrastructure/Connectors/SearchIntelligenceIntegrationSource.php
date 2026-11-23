<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Infrastructure\Connectors;

use App\Modules\SearchIntelligence\Domain\Models\Integration as SearchIntelligenceIntegration;
use App\Shared\Contracts\Connectors\ConnectorIntegrationSourceInterface;

/** #9846 — connecteurs SEO / SEA (GSI). */
final class SearchIntelligenceIntegrationSource implements ConnectorIntegrationSourceInterface
{
    public function key(): string
    {
        return 'search_intelligence';
    }

    public function label(): string
    {
        return 'Connecteurs SEO / SEA (GSI)';
    }

    public function category(): string
    {
        return 'analytics';
    }

    public function href(): string
    {
        return '/search-intel/integrations';
    }

    public function detail(): string
    {
        return 'Search Console, GA4, Google Ads, Microsoft Ads.';
    }

    /** @return list<'platform'|'tenant'> */
    public function audiences(): array
    {
        return ['platform'];
    }

    public function count(): int
    {
        return SearchIntelligenceIntegration::query()->count();
    }
}
