<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Application\Actions;

use App\Modules\HealthManager\Domain\Models\HealthPatient;
use App\Modules\HealthManager\Infrastructure\Services\HealthPatientNumberService;

/**
 * Cas d'usage « enregistrer un patient » — HC-003 (#7787, BC-31).
 *
 * Extrait de `HealthPatientController::store` (BOS-024b, #8213) : mapping
 * des clairs API vers les colonnes chiffrées au repos, statut initial
 * `active`, MRN `PAT-YYYY-NNNN` généré serveur (séquence tenant/année
 * race-safe portée par le service).
 */
final class RegisterHealthPatientAction
{
    /**
     * Clairs API ↔ colonnes chiffrées du modèle. Source unique, partagée
     * avec `UpdateHealthPatientAction` (mêmes règles de persistance).
     *
     * @var array<string, string>
     */
    public const ENCRYPTED_INPUTS = [
        'birth_date' => 'birth_date_encrypted',
        'phone' => 'phone_encrypted',
        'email' => 'email_encrypted',
        'address' => 'address_encrypted',
        'emergency_contact_name' => 'emergency_contact_name_encrypted',
        'emergency_contact_phone' => 'emergency_contact_phone_encrypted',
        'insurance_provider' => 'insurance_provider_encrypted',
        'insurance_number' => 'insurance_number_encrypted',
        'allergies' => 'allergies_encrypted',
        'medical_history' => 'medical_history_encrypted',
    ];

    public function __construct(private readonly HealthPatientNumberService $numberService) {}

    /**
     * @param  array<string, mixed>  $validated  Payload validé (StoreHealthPatientRequest).
     */
    public function execute(string $companyId, array $validated): HealthPatient
    {
        $payload = self::mapEncryptedInputs($validated);
        $payload['status'] = HealthPatient::STATUS_ACTIVE;

        // MRN serveur, séquence tenant/année race-safe (transaction + verrou).
        return $this->numberService->createWithMrn($companyId, $payload);
    }

    /**
     * Mappe les clairs API (`phone`, `email`…) vers les colonnes chiffrées
     * au repos (`*_encrypted`).
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function mapEncryptedInputs(array $validated): array
    {
        foreach (self::ENCRYPTED_INPUTS as $input => $column) {
            if (array_key_exists($input, $validated)) {
                $validated[$column] = $validated[$input];
                unset($validated[$input]);
            }
        }

        return $validated;
    }
}
