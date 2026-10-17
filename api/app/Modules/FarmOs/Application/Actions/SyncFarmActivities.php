<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Application\Actions;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FarmOs\Domain\Enums\FarmActivity;
use App\Modules\FarmOs\Domain\Enums\FarmCapability;
use App\Modules\FarmOs\Domain\Models\FarmActivityRecord;
use App\Modules\FarmOs\Domain\Models\FarmCapabilitySetting;
use Illuminate\Support\Carbon;

/**
 * Farm OS — Enregistrement des activités déclarées à l'étape 1 de l'onboarding.
 *
 * IDEMPOTENT et NON DESTRUCTIF :
 *   - l'ajout d'une activité est un upsert (contrainte unique
 *     `company_id + activity`) ;
 *   - une activité retirée de la sélection est simplement CLOSE (`ended_at`),
 *     jamais supprimée : l'historique et les données restent intacts
 *     (exigence FR-1.7 — ajouter une activité plus tard ne recrée rien).
 *
 * Pureté des couches (issue #6568) : aucune façade Laravel ici. La transaction
 * est portée par la connexion du modèle (`getConnection()`) et les noms de
 * tables viennent des modèles (`getTable()`) — `DB` et `Schema` sont des
 * façades réservées aux couches Interfaces/Infrastructure.
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

        $connection = FarmActivityRecord::query()->getConnection();
        // Nom de table pris AU MODÈLE (source unique) : le préfixe éventuel est
        // appliqué par la connexion, aucune façade n'est requise.
        $capabilityTable = (new FarmCapabilitySetting)->getTable();

        $connection->transaction(function () use ($company, $requested, $now, $connection, $capabilityTable): void {
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

            // Les activités disent ce que l'exploitation FAIT. `$requested` ne
            // contient que des valeurs déjà résolues par l'enum FarmActivity
            // (allowlist fail-closed) : aucun réglage d'outil n'est créé pour
            // une activité inconnue. Une activité AJOUTÉE plus tard réactive le
            // bloc de décision, elle ne supprime jamais un outil déjà décidé.
            if ($requested !== []) {
                foreach (FarmCapability::values() as $capability) {
                    $connection->table($capabilityTable)->insertOrIgnore([
                        'company_id' => $company->id,
                        'capability' => $capability,
                        'enabled' => false,
                        'source' => 'onboarding',
                        'decided_at' => $now,
                    ]);
                }
            }
        });

        return $requested;
    }
}
