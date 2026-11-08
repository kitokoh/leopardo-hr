<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Application\Actions;

use App\Modules\Recruitment\Domain\Models\JobPosting;
use Illuminate\Support\Carbon;

/**
 * BC-10 RECRUITMENT — publication d'une offre depuis la console plateforme.
 *
 * Différence essentielle avec `PublishJobPostingAction` (chemin tenant) : ici
 * l'auteur n'est PAS un employé du tenant, c'est un collaborateur Leopardo. On
 * marque donc l'offre `published_by_platform` et on conserve l'identifiant du
 * super admin dans `platform_created_by` — sans quoi une offre publiée par la
 * plateforme serait indiscernable d'une offre du client, et le client ne
 * pourrait pas savoir qui a publié quoi sur sa page carrières.
 *
 * L'écriture se fait DANS le contexte du tenant cible (appelant :
 * `TenantManager::withinTenant`), jamais par un `company_id` mass-assigné
 * (#7646).
 */
class PublishPlatformJobPostingAction
{
    public function execute(JobPosting $jobPosting, int $platformActorId): JobPosting
    {
        $jobPosting->update([
            'status' => 'published',
            'published_at' => Carbon::now(),
            'published_by_platform' => true,
            'platform_created_by' => $platformActorId,
        ]);

        return $jobPosting->fresh() ?? $jobPosting;
    }
}
