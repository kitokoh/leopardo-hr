<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Application\Actions;

use App\Modules\HealthManager\Domain\Models\HealthPractitioner;
use App\Modules\HealthManager\Domain\Models\HealthPractitionerSpecialty;
use Illuminate\Database\ConnectionInterface;

/**
 * Cas d'usage « enregistrer un praticien » — HC-002 (#7786, BC-31).
 *
 * Extrait de `HealthPractitionerController::store` (BOS-024b, #8213) :
 * création + synchronisation des spécialités (n-n) en transaction ; le
 * pivot porte `company_id` sur chaque ligne (FK composites anti
 * cross-tenant).
 */
final class RegisterHealthPractitionerAction
{
    public function __construct(private readonly ConnectionInterface $db) {}

    /**
     * @param  array<string, mixed>  $validated  Payload validé (StoreHealthPractitionerRequest).
     */
    public function execute(string $companyId, array $validated): HealthPractitioner
    {
        /** @var list<int|string> $specialtyIds */
        $specialtyIds = $validated['specialty_ids'] ?? [];
        unset($validated['specialty_ids']);

        return $this->db->transaction(function () use ($companyId, $validated, $specialtyIds): HealthPractitioner {
            /** @var HealthPractitioner $practitioner */
            $practitioner = HealthPractitioner::query()->create(array_merge($validated, [
                'company_id' => $companyId,
            ]));

            self::syncSpecialties($practitioner, $specialtyIds, $companyId);

            return $practitioner->refresh();
        });
    }

    /**
     * Synchronise le pivot praticien ↔ spécialités (company_id sur chaque
     * ligne — jamais de rattachement cross-tenant, ids déjà validés).
     * Source unique partagée avec `UpdateHealthPractitionerAction`.
     *
     * @param  list<int|string>  $specialtyIds
     */
    public static function syncSpecialties(HealthPractitioner $practitioner, array $specialtyIds, string $companyId): void
    {
        $ids = array_values(array_unique(array_map(
            static fn (int|string $id): int => (int) $id,
            $specialtyIds
        )));

        HealthPractitionerSpecialty::query()
            ->where('company_id', $companyId)
            ->where('practitioner_id', $practitioner->getAttribute('id'))
            ->whereNotIn('specialty_id', $ids)
            ->delete();

        foreach ($ids as $specialtyId) {
            HealthPractitionerSpecialty::query()->firstOrCreate([
                'company_id' => $companyId,
                'practitioner_id' => (int) $practitioner->getAttribute('id'),
                'specialty_id' => $specialtyId,
            ]);
        }
    }
}
