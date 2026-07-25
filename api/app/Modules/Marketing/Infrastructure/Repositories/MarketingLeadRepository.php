<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Infrastructure\Repositories;

use App\Modules\Marketing\Application\DTOs\CreateMarketingLeadDTO;
use App\Modules\Marketing\Domain\Contracts\MarketingLeadRepositoryInterface;
use App\Modules\Marketing\Domain\Models\MarketingLead;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MarketingLeadRepository implements MarketingLeadRepositoryInterface
{
    public function create(CreateMarketingLeadDTO $dto): MarketingLead
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET search_path TO public');
        }

        return MarketingLead::query()->create([
            'external_id' => $dto->externalId,
            'type' => $dto->type,
            'email' => $dto->email,
            'locale' => $dto->locale,
            'country' => $dto->country,
            'page' => $dto->page,
            'source' => $dto->source,
            'campaign' => $dto->campaign,
            'ip' => $dto->ip,
            'referrer' => $dto->referrer,
            'payload' => $dto->payload,
            'status' => MarketingLead::STATUS_NEW,
            'crm_forwarded' => $dto->crmForwarded,
            'email_forwarded' => $dto->emailForwarded,
            'captured_at' => $dto->capturedAt,
        ]);
    }

    /**
     * @return Collection<int, MarketingLead>
     */
    public function all(): Collection
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET search_path TO public');
        }

        return MarketingLead::query()->orderByDesc('created_at')->get();
    }
}
