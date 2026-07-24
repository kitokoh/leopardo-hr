<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Jobs\DispatchPlatformAnnouncementToCompanyJob;
use App\Modules\Platform\Domain\Models\PlatformAnnouncement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * PA2-COMM-005 — Super-admin broadcasts a platform-wide announcement
 * (maintenance, new feature, incident, action required) to every company,
 * or an explicit subset of companies. Publishing stores the announcement
 * in the `public` schema and queues one tenant-scoped fan-out job per
 * targeted company, mirroring the tenant-level AnnouncementService
 * (PA2-COMM-004) pattern.
 */
class PlatformAnnouncementService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function publish(SuperAdmin $author, array $data): PlatformAnnouncement
    {
        $audienceType = $data['audience_type'] ?? PlatformAnnouncement::AUDIENCE_ALL;
        $companyIds = $this->resolveTargetCompanyIds($audienceType, $data['company_ids'] ?? []);

        $announcement = DB::transaction(function () use ($author, $data, $audienceType, $companyIds): PlatformAnnouncement {
            $announcement = PlatformAnnouncement::create([
                'created_by' => $author->id,
                'title' => $data['title'],
                'body' => $data['body'],
                'category' => $data['category'] ?? PlatformAnnouncement::CATEGORY_NEWS,
                'severity' => $data['severity'] ?? 'normal',
                'audience_type' => $audienceType,
                'published_at' => now(),
                'expires_at' => $data['expires_at'] ?? null,
                'companies_count' => $companyIds->count(),
            ]);

            if ($audienceType === PlatformAnnouncement::AUDIENCE_COMPANIES) {
                $announcement->companies()->sync($companyIds->all());
            }

            return $announcement;
        });

        foreach ($companyIds as $companyId) {
            DispatchPlatformAnnouncementToCompanyJob::dispatch($announcement->id, $companyId);
        }

        return $announcement;
    }

    /**
     * @param  list<string>  $explicitCompanyIds
     * @return Collection<int, string>
     */
    private function resolveTargetCompanyIds(string $audienceType, array $explicitCompanyIds): Collection
    {
        if ($audienceType === PlatformAnnouncement::AUDIENCE_COMPANIES) {
            if ($explicitCompanyIds === []) {
                return collect();
            }

            return Company::query()
                ->whereIn('id', $explicitCompanyIds)
                ->pluck('id')
                ->map(fn ($id) => (string) $id);
        }

        return Company::query()
            ->whereNotIn('status', ['suspended', 'expired'])
            ->pluck('id')
            ->map(fn ($id) => (string) $id);
    }
}
