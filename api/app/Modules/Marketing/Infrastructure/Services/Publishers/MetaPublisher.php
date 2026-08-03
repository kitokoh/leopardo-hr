<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Infrastructure\Services\Publishers;

/**
 * Publisher Meta (issue #1433) : couvre les pages et groupes Facebook,
 * ainsi qu'Instagram et Threads (comptes Meta). Publie via l'agregateur
 * Ayrshare — voir `AbstractAyrsharePublisher`.
 */
class MetaPublisher extends AbstractAyrsharePublisher
{
    /** @return array<int, string> */
    public function supportedPlatforms(): array
    {
        return ['facebook_page', 'facebook_group', 'instagram', 'threads'];
    }
}
