<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Modules\FuelStation\Domain\Exceptions\FuelStationManifestInvalidException;

/**
 * Validation et exposition du manifest de solution FuelStation (issue #5795).
 *
 * Le manifest est chargé depuis `config/fuelstation.php` puis validé contre
 * l'allowlist : toute clé inconnue, toute valeur hors bornes (maturité,
 * permissions, dépendances) est rejetée — un manifest invalide ne peut pas
 * être servi ni activé.
 */
final class FuelStationManifestService
{
    /** @var array<string, mixed>|null */
    private ?array $validated = null;

    /**
     * Retourne le manifest validé.
     *
     * @return array<string, mixed>
     */
    public function manifest(): array
    {
        if ($this->validated !== null) {
            return $this->validated;
        }

        $raw = config('fuelstation.solution');
        if (! is_array($raw)) {
            throw new FuelStationManifestInvalidException('Manifest FuelStation absent de la configuration.');
        }

        $this->validated = $this->validate($raw);

        return $this->validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function validate(array $raw): array
    {
        $allowlist = is_array($raw['allowlist'] ?? null) ? $raw['allowlist'] : [];
        foreach (array_keys($raw) as $key) {
            if ($key === 'allowlist') {
                continue;
            }
            if (! in_array($key, $allowlist, true)) {
                throw new FuelStationManifestInvalidException('Clé de manifest inconnue : '.$key);
            }
        }

        $key = isset($raw['key']) && is_string($raw['key']) ? $raw['key'] : '';
        $name = isset($raw['name']) && is_string($raw['name']) ? $raw['name'] : '';
        $version = isset($raw['version']) && is_string($raw['version']) ? $raw['version'] : '';
        $maturity = isset($raw['maturity']) && is_string($raw['maturity']) ? $raw['maturity'] : '';

        if ($key === '' || $name === '' || $version === '') {
            throw new FuelStationManifestInvalidException('Manifest incomplet (key/name/version requis).');
        }
        if (! in_array($maturity, ['pilot', 'ga'], true)) {
            throw new FuelStationManifestInvalidException('Maturité de manifest inconnue : '.$maturity);
        }

        $permissions = is_array($raw['permissions'] ?? null) ? $raw['permissions'] : [];
        $dependencies = is_array($raw['dependencies'] ?? null) ? $raw['dependencies'] : [];

        if ($permissions === [] || $dependencies === []) {
            throw new FuelStationManifestInvalidException('Permissions ou dépendances manquantes.');
        }

        return [
            'key' => $key,
            'name' => $name,
            'version' => $version,
            'maturity' => $maturity,
            'permissions' => array_values(array_filter($permissions, 'is_string')),
            'dependencies' => array_map('boolval', $dependencies),
        ];
    }
}
