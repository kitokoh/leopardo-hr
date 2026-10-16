<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Application\Actions;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FarmOs\Domain\Enums\FarmActivity;
use App\Modules\FarmOs\Domain\Enums\FarmCapability;
use App\Modules\FarmOs\Domain\Models\FarmActivityRecord;
use App\Modules\FarmOs\Domain\Models\FarmCapabilitySetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Farm OS — Enregistrement des activités déclarées à l'étape 1 de l'onboarding.
 *
 * IDEMPOTENT et NON DESTRUCTIF :
 *   - l'ajout d'une activité est un upsert (contrainte unique
 *     `company_id + activity`) ;
 *   - une activité retirée de la sélection est simplement CLOSE (`ended_at`),
 *     jamais supprimée : l'historique et les données restent intacts
 *     (exigence FR-1.7 — ajouter une activité plus tard ne recrée rien).
 */
final class SyncFarmActivities
{
    /**
     * @param  list<string>  $activities
     * @return list<string>
     */
    public function execute(Company $company, array $activities): array
    {
        $requested = [];

        foreach ($activities as $activity) {
            $enum = FarmActivity::tryFrom((string) $activity);

            if ($enum !== null) {
                $requested[] = $enum->value;
            }
        }

        $requested = array_values(array_unique($requested));
        $now = Carbon::now();

        DB::transaction(function () use ($company, $requested, $now): void {
            foreach ($requested as $index => $activity) {
                FarmActivityRecord::query()->updateOrCreate(
                    ['company_id' => $company->id, 'activity' => $activity],
                    [
                        'is_primary' => $index === 0,
                        'declared_at' => $now,
                        'ended_at' => null,
                    ]
                );
            }

            // Activation ultérieure possible : les autres activités sont closes,
            // pas effacées.
            FarmActivityRecord::query()
                ->when($requested !== [], fn ($query) => $query->whereNotIn('activity', $requested))
                ->whereNull('ended_at')
                ->update(['ended_at' => $now]);

            // Les activités disent ce que l'exploitation FAIT, elles ne
            // suppriment jamais un outil déjà décidé par l'utilisateur.
            foreach ($requested as $activity) {
                $enum = FarmActivity::tryFrom($activity);

                if ($enum === null) {
                    continue;
                }

                foreach (FarmCapability::values() as $capability) {
                    FarmCapabilitySetting::query()->firstOrCreate(
                        ['company_id' => $company->id, 'capability' => $capability],
                        ['enabled' => false, 'source' => 'onboarding', 'decided_at' => $now]
                    );
                }
            }
        });

        return $requested;
    }
}
