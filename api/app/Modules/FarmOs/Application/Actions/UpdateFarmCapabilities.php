<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Application\Actions;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FarmOs\Domain\Enums\FarmCapability;
use App\Modules\FarmOs\Domain\Models\FarmCapabilitySetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Farm OS — Mise à jour des outils activés (étape 3 de l'onboarding, puis
 * écran « Mes outils »).
 *
 * L'utilisateur ACCEPTE, REFUSE ou MODIFIE : une capacité explicitement refusée
 * est persistée (`enabled = false`) et respectée — Farm OS ne réactive jamais un
 * outil dans le dos de l'exploitant.
 */
final class UpdateFarmCapabilities
{
    /**
     * @param  array<string, bool>  $decisions  capacité ⇒ activée ?
     * @return array<string, bool>
     */
    public function execute(Company $company, array $decisions, string $source = 'onboarding', ?int $employeeId = null): array
    {
        $now = Carbon::now();

        DB::transaction(function () use ($company, $decisions, $source, $employeeId, $now): void {
            foreach ($decisions as $capability => $enabled) {
                $enum = FarmCapability::tryFrom((string) $capability);

                if ($enum === null) {
                    continue;
                }

                FarmCapabilitySetting::query()->updateOrCreate(
                    ['company_id' => $company->id, 'capability' => $enum->value],
                    [
                        'enabled' => (bool) $enabled,
                        'source' => $source,
                        'decided_by' => $employeeId,
                        'decided_at' => $now,
                    ]
                );
            }
        });

        return FarmCapabilitySetting::decisions();
    }
}
