<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceKiosk;
use App\Modules\Attendance\Infrastructure\Services\KioskCredentialFactory;
use App\Modules\Attendance\Infrastructure\Services\KioskViewBuilder;

/**
 * #7962 (suite de #8944) — cas d'usage : provisionner un kiosque.
 *
 * Extrait de `KioskController::doRegister()` (79 lignes de logique métier dans
 * un contrôleur de 859). Ce qui reste dans le contrôleur : la permission, la
 * bascule de `search_path` et le code HTTP 201.
 *
 * ## Pourquoi les secrets sont générés ICI et non dans le contrôleur
 *
 * Le `device_code` et le `sync_token` en clair ne sont rendus qu'une fois, à la
 * création. Les générer au plus près de la persistance rend impossible le cas
 * « un secret a été généré mais la ligne a échoué » : les deux valeurs et la
 * ligne sont produits par le même appel, et l'appelant reçoit ce qu'il doit
 * transmettre au terminal.
 *
 * ## Pourquoi `card` devient `badge`
 *
 * Vocabulaire ZKTeco vs domaine ATT-002. La normalisation est faite ici, à la
 * frontière d'écriture, pour que la base ne contienne jamais deux mots pour la
 * même méthode de pointage.
 */
class RegisterKiosk
{
    public function __construct(
        private readonly KioskViewBuilder $view,
        private readonly KioskCredentialFactory $credentials,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  Données déjà validées par la requête HTTP
     * @return array{kiosk: AttendanceKiosk, device_code: string, sync_token: string}
     */
    public function execute(Company $company, array $payload): array
    {
        $plainDeviceCode = $this->credentials->newDeviceCode();
        $plainToken = $this->credentials->newSyncToken();

        $kiosk = AttendanceKiosk::query()->create([
            'company_id' => $company->id,
            'name' => $payload['name'],
            'location_label' => $payload['location_label'] ?? null,
            'biometric_mode' => $payload['biometric_mode'] ?? 'fingerprint',
            'trusted_device_label' => $payload['trusted_device_label'] ?? null,
            'site_id' => $payload['site_id'] ?? null,
            'punch_methods' => isset($payload['punch_methods']) && is_array($payload['punch_methods'])
                ? $this->normalizePunchMethods($payload['punch_methods'])
                : null,
            // Issue #5588 : le device_code n'est plus stocké en clair (hash
            // déterministe sha256, lookup par égalité). Le code en clair n'est
            // retourné qu'à la création (provisioning du kiosque).
            'device_code' => $this->credentials->storeDeviceCode($plainDeviceCode),
            'sync_token_hash' => $this->credentials->storeSyncToken($plainToken),
            'status' => 'active',
        ]);

        return [
            'kiosk' => $kiosk,
            'device_code' => $plainDeviceCode,
            'sync_token' => $plainToken,
        ];
    }

    /**
     * Vue à renvoyer au gestionnaire, secrets en clair inclus.
     *
     * @return array<string, mixed>
     */
    public function responsePayload(AttendanceKiosk $kiosk, string $deviceCode, string $syncToken): array
    {
        return array_replace($this->view->serialize($kiosk), [
            'device_code' => $deviceCode,
            'sync_token' => $syncToken,
        ]);
    }

    /**
     * Normalise la matrice envoyée au provisioning (BIO-006) : `card`
     * (vocabulaire ZKTeco) est stocké `badge` (domaine ATT-002).
     *
     * @param  array<array-key, mixed>  $methods
     * @return list<string>
     */
    private function normalizePunchMethods(array $methods): array
    {
        $normalized = [];
        foreach ($methods as $method) {
            if (! is_string($method)) {
                continue;
            }
            $normalized[] = $method === 'card' ? 'badge' : $method;
        }

        return array_values(array_unique(array_filter(
            $normalized,
            static fn (string $method): bool => in_array($method, AttendanceKiosk::KIOSK_PUNCH_METHODS_ALL, true)
        )));
    }
}
