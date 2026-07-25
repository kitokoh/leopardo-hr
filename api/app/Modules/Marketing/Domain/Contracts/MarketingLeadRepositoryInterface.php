<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Domain\Contracts;

use App\Modules\Marketing\Application\DTOs\CreateMarketingLeadDTO;
use App\Modules\Marketing\Domain\Models\MarketingLead;
use Illuminate\Support\Collection;

interface MarketingLeadRepositoryInterface
{
    public function create(CreateMarketingLeadDTO $dto): MarketingLead;

    /**
     * @return Collection<int, MarketingLead>
     */
    public function all(): Collection;
}
