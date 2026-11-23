<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Infrastructure\Connectors;

use App\Modules\Recruitment\Domain\Models\JobSource;
use App\Shared\Contracts\Connectors\ConnectorIntegrationSourceInterface;

/** #9846 — agrégateurs d'offres d'emploi (BC-10). */
final class JobSourceIntegrationSource implements ConnectorIntegrationSourceInterface
{
    public function key(): string
    {
        return 'job_sources';
    }

    public function label(): string
    {
        return 'Agrégateurs d\'offres d\'emploi';
    }

    public function category(): string
    {
        return 'marketplace';
    }

    public function href(): string
    {
        return '/recruitment/sources';
    }

    public function detail(): string
    {
        return 'Connecteurs de diffusion d\'offres (BC-10) : publication vers les job boards.';
    }

    /** @return list<'platform'|'tenant'> */
    public function audiences(): array
    {
        return ['platform'];
    }

    public function count(): int
    {
        return JobSource::query()->count();
    }
}
