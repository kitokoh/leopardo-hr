<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Infrastructure\Services\Publishers;

/**
 * Publisher LinkedIn (issue #1433). Publie via l'agregateur Ayrshare —
 * voir `AbstractAyrsharePublisher`.
 */
class LinkedInPublisher extends AbstractAyrsharePublisher
{
    /** @return array<int, string> */
    public function supportedPlatforms(): array
    {
        return ['linkedin'];
    }
}
