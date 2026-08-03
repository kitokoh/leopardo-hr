<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Infrastructure\Services\Publishers;

/**
 * Publisher X/Twitter (issue #1433). Publie via l'agregateur Ayrshare —
 * voir `AbstractAyrsharePublisher`.
 */
class TwitterPublisher extends AbstractAyrsharePublisher
{
    /** @return array<int, string> */
    public function supportedPlatforms(): array
    {
        return ['twitter'];
    }
}
