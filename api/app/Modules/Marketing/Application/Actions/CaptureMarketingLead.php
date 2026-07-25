<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Application\Actions;

use App\Modules\Marketing\Application\DTOs\CreateMarketingLeadDTO;
use App\Modules\Marketing\Domain\Contracts\MarketingLeadRepositoryInterface;
use App\Modules\Marketing\Domain\Models\MarketingLead;

/**
 * PA2-MKT-007 — Persists a lead captured by the public vitrine forms
 * (signup, demo_request, newsletter, contact) into the durable
 * `marketing_leads` table, so the platform CRM pipeline (PA2-ADM-004) can
 * list/filter/qualify leads regardless of whether the best-effort
 * CRM/email webhook forwarding succeeded.
 *
 * Idempotent on `external_id`: a retried request from the web front-end
 * (e.g. after a network blip) updates the existing row instead of creating
 * a duplicate lead.
 */
class CaptureMarketingLead
{
    public function __construct(
        private readonly MarketingLeadRepositoryInterface $leads,
    ) {}

    public function execute(CreateMarketingLeadDTO $dto): MarketingLead
    {
        $existing = MarketingLead::query()
            ->where('external_id', $dto->externalId)
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->leads->create($dto);
    }
}
