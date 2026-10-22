<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Application\Actions;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FarmOs\Domain\Enums\FarmCapability;
use App\Modules\FarmOs\Domain\Models\FarmCapabilitySetting;
use Illuminate\Support\Carbon;

/**
 * Farm OS — Mise à jour des outils activés (étape 3 de l'onboarding, puis
 * écran « Mes outils »).
 *
 * L'utilisateur ACCEPTE, REFUSE ou MODIFIE : une capacité explicitement refusée
 * est persistée (`enabled = false`) et respectée — Farm OS ne réactive jamais un
 * outil dans le dos de l'exploitant.
 *
 * Pureté des couches (issue #6568) : aucune façade Laravel ici — mise à jour en
 * place puis insertion si la ligne n'existe pas encore, sur la connexion du
 * modèle (`getConnection()`).
 */
final class UpdateFarmCapabilitiesAction
{
    /**
     * @param  array<string, bool>  $decisions  capacité ⇒ activée ?
     * @return array<string, bool>
     */
    public function execute(Company $company, array $decisions, string $source = 'onboarding', ?int $employeeId = null): array
    {
        $now = Carbon::now();

        $connection = FarmCapabilitySetting::query()->getConnection();
        // Nom de table pris AU MODÈLE : le préfixe éventuel est appliqué par la
        // connexion, aucune façade Laravel n'est nécessaire (pureté #6568).
        $capabilityTable = (new FarmCapabilitySetting)->getTable();

        $connection->transaction(function () use ($company, $decisions, $source, $employeeId, $now, $connection, $capabilityTable): void {
            foreach ($decisions as $capability => $enabled) {
                $enum = FarmCapability::tryFrom((string) $capability);

                if ($enum === null) {
                    continue;
                }

                $updated = $connection->table($capabilityTable)
                    ->where('company_id', $company->id)
                    ->where('capability', $enum->value)
                    ->update([
                        'enabled' => (bool) $enabled,
                        'source' => $source,
                        'decided_by' => $employeeId,
                        'decided_at' => $now,
                        'updated_at' => $now,
                    ]);

                if ($updated === 0) {
                    $connection->table($capabilityTable)->insertOrIgnore([
                        'company_id' => $company->id,
                        'capability' => $enum->value,
                        'enabled' => (bool) $enabled,
                        'source' => $source,
                        'decided_by' => $employeeId,
                        'decided_at' => $now,
                    ]);
                }
            }
        });

        return FarmCapabilitySetting::decisions();
    }
}
